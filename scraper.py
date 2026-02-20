import os
import time
import json
import sqlite3
import re
import requests
import warnings
import fitz  # PyMuPDF
from PIL import Image
from bs4 import BeautifulSoup
from datetime import datetime
import sys
import zipfile # Adicionado para empacotar o .zip

# --- SUPRESSÃO DE AVISOS ---
warnings.filterwarnings("ignore")
import google.generativeai as genai

# --- CONFIGURAÇÕES GERAIS ---
BASE_DIR = "Dados_Scraper"
BANCAS_FILE = "bancas.txt"
API_KEY_FILE = "apikey.txt"
ESTADO_SCRAPING_FILE = os.path.join(BASE_DIR, "estado_scraping.json")

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}

SELECTED_MODEL_NAME = ''
CURRENT_API_KEY = ''
NOME_BANCA_ATUAL = None # Variável global para rastrear a banca no encerramento

# --- FUNÇÕES DE INICIALIZAÇÃO E UTILIDADES ---

def log(mensagem):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {mensagem}")

def sanitize_name(name):
    return re.sub(r'[\\/*?:"<>|]', "", name).strip()

def inicializar_estrutura():
    if not os.path.exists(BASE_DIR):
        os.makedirs(BASE_DIR)
            
    if not os.path.exists(ESTADO_SCRAPING_FILE):
        with open(ESTADO_SCRAPING_FILE, "w", encoding="utf-8") as f:
            json.dump({}, f)
            
    if not os.path.exists(BANCAS_FILE):
        with open(BANCAS_FILE, "w", encoding="utf-8") as f:
            f.write('"FGV", "https://www.pciconcursos.com.br/provas/fgv"\n')
            f.write('"CESPE", "https://www.pciconcursos.com.br/provas/cespe"\n')
        print(f"⚠️ Arquivo '{BANCAS_FILE}' criado com exemplos. Edite-o se necessário.")

def carregar_bancas():
    inicializar_estrutura()
    bancas = []
    with open(BANCAS_FILE, "r", encoding="utf-8") as f:
        for linha in f:
            if not linha.strip(): continue
            partes = linha.strip().split(',')
            if len(partes) >= 2:
                nome = partes[0].strip().strip('"').strip("'")
                url = partes[1].strip().strip('"').strip("'")
                bancas.append({"nome": nome, "url": url})
    return bancas

def selecionar_banca():
    bancas = carregar_bancas()
    if not bancas:
        print(f"❌ Nenhuma banca configurada no '{BANCAS_FILE}'.")
        sys.exit(1)
        
    print("\n" + "="*50)
    print("📚 SELEÇÃO DE BANCA PARA SCRAPING")
    print("="*50)
    for i, b in enumerate(bancas, 1):
        print(f"[{i}] {b['nome']} ({b['url']})")
        
    while True:
        try:
            escolha = input("\n👉 Digite o NÚMERO da banca: ")
            idx = int(escolha) - 1
            if 0 <= idx < len(bancas):
                return bancas[idx]
            print("❌ Número inválido.")
        except ValueError:
            print("❌ Digite apenas o número.")

# --- 1. GESTÃO DE API KEYS E MODELOS ---

def carregar_chaves_do_arquivo():
    if not os.path.exists(API_KEY_FILE):
        print(f"\n❌ Arquivo '{API_KEY_FILE}' não encontrado!")
        sys.exit(1)
    
    chaves_encontradas = []
    with open(API_KEY_FILE, "r", encoding="utf-8") as f:
        conteudo = f.read()
        
    if '"' not in conteudo:
        return [{'apelido': 'Chave Única (Padrão)', 'key': conteudo.strip()}]
    
    padrao = r'"([^"]+)"\s*,\s*"([^"]+)"'
    matches = re.findall(padrao, conteudo)
    
    for apelido, chave in matches:
        if len(chave) > 10:
            chaves_encontradas.append({'apelido': apelido, 'key': chave})

    if not chaves_encontradas:
        print(f"\n❌ Nenhuma chave válida encontrada no padrão \"Nome\", \"Key\".")
        sys.exit(1)
        
    return chaves_encontradas

def selecionar_api_key():
    print("\n" + "="*50)
    print("🔑 SELEÇÃO DE CONTA (API KEY)")
    print("="*50)
    
    chaves = carregar_chaves_do_arquivo()
    if len(chaves) == 1:
        print(f"\nℹ️ Apenas uma chave encontrada: {chaves[0]['apelido']}")
        genai.configure(api_key=chaves[0]['key'])
        return chaves[0]['key']

    for i, item in enumerate(chaves, 1):
        chave_mascarada = f"...{item['key'][-6:]}"
        print(f"[{i}] {item['apelido']} \t({chave_mascarada})")
    
    while True:
        try:
            escolha = input("\n👉 Digite o NÚMERO da chave que deseja usar: ")
            idx = int(escolha) - 1
            if 0 <= idx < len(chaves):
                escolhida = chaves[idx]
                print(f"\n✅ CONTA SELECIONADA: {escolhida['apelido']}")
                genai.configure(api_key=escolhida['key'])
                return escolhida['key']
        except ValueError:
            pass
        print("❌ Inválido.")

def escolher_modelo_interativo():
    print("\n" + "="*50)
    print("🤖 CONECTANDO AO GOOGLE GENAI PARA LISTAR MODELOS...")
    print("="*50)
    
    try:
        modelos_disponiveis = [m for m in genai.list_models() if 'generateContent' in m.supported_generation_methods]
        modelos_disponiveis.sort(key=lambda x: x.name)

        for i, model in enumerate(modelos_disponiveis, 1):
            nome_display = model.name.replace('models/', '')
            print(f"[{i}] {nome_display} \t({model.display_name})")

        while True:
            try:
                escolha = input("\n👉 Digite o NÚMERO do modelo desejado: ")
                idx = int(escolha) - 1
                if 0 <= idx < len(modelos_disponiveis):
                    modelo_candidato = modelos_disponiveis[idx].name
                    print(f"🚀 Usando: {modelo_candidato}\n" + "="*50)
                    return modelo_candidato
            except ValueError:
                pass
            print("❌ Inválido.")
    except Exception as e:
        print(f"❌ Erro ao listar modelos: {e}. Usando fallback.")
        return 'models/gemini-1.5-flash'

# --- 2. GERENCIAMENTO DE ESTADO ---

def obter_pagina_inicial(nome_banca):
    with open(ESTADO_SCRAPING_FILE, "r", encoding="utf-8") as f:
        estado = json.load(f)
    return estado.get(nome_banca, {}).get("ultima_pagina", 1)

def salvar_progresso(nome_banca, pagina):
    with open(ESTADO_SCRAPING_FILE, "r", encoding="utf-8") as f:
        estado = json.load(f)
        
    if nome_banca not in estado:
        estado[nome_banca] = {}
    estado[nome_banca]["ultima_pagina"] = pagina
    
    with open(ESTADO_SCRAPING_FILE, "w", encoding="utf-8") as f:
        json.dump(estado, f, indent=4)

def registrar_revisao_manual(banca, prova_id, cargo, numero_questao, motivo):
    arquivo_revisao = os.path.join(BASE_DIR, banca, f"revisao_{banca.lower()}.txt")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    linha = f"[{timestamp}] PROVA ID: {prova_id} | CARGO: {cargo} | QUESTÃO: {numero_questao} | MOTIVO: {motivo}\n"
    with open(arquivo_revisao, "a", encoding="utf-8") as f:
        f.write(linha)

# --- 3. BANCO DE DADOS RELACIONAL ---

def setup_db(banca_nome):
    """
    Cria (ou abre) o banco de dados SQLite da banca.
    ---
    IMPORTANTE: Os nomes das colunas aqui foram alinhados com o schema de produção
    do sistema Laravel (tabelas: questions, subjects, question_subject).
    Isso garante que o importador backend precise de zero mapeamento e possa
    espelhar os dados diretamente.
    ---
    Mapeamento de nomes antigos → novos:
      provas.banca        → organization
      provas.cargo        → role
      provas.ano          → year
      provas.orgao        → institution
      provas.instituicao  → origin
      provas.url_origem   → source_url
      provas.data_extracao→ extracted_at
      questoes.enunciado  → statement
      questoes.imagens_arquivos → image_path
      questoes.revisao_manual   → review_status ('pending'|'approved')
      disciplinas.nome    → name
    """
    banca_dir = os.path.join(BASE_DIR, banca_nome)
    if not os.path.exists(banca_dir):
        os.makedirs(banca_dir)
        
    db_path = os.path.join(banca_dir, f"banco_{banca_nome.lower()}.db")
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    # -----------------------------------------------------------------
    # TABELA: provas
    # Espelha os campos de metadados da prova que serão usados para
    # preencher os campos organization, institution, role, year das questions.
    # -----------------------------------------------------------------
    cursor.execute('''CREATE TABLE IF NOT EXISTS provas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization TEXT,    -- Banca (ex: FGV) → questions.organization
        role TEXT,            -- Cargo (ex: Analista) → questions.role
        year TEXT,            -- Ano da prova → questions.year
        institution TEXT,     -- Órgão (ex: TJ-SP) → questions.institution
        origin TEXT,          -- Entidade aplicadora → questions.origin
        source_url TEXT UNIQUE, -- URL de origem para deduplicação
        extracted_at TEXT     -- Timestamp da extração
    )''')
    
    # -----------------------------------------------------------------
    # TABELA: questoes
    # Espelha a tabela 'questions' de produção.
    # OBS: 'alternativas' ainda é JSON aqui pois o importer do Laravel
    #      é responsável por explodir isso em linhas de 'question_alternatives'.
    # -----------------------------------------------------------------
    cursor.execute('''CREATE TABLE IF NOT EXISTS questoes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        prova_id INTEGER,
        numero TEXT,
        statement TEXT,           -- Enunciado → questions.statement
        alternativas TEXT,        -- JSON {"A":"...","B":"..."} → explodido no importer
        gabarito TEXT,            -- Letra correta (ex: "C") → question_alternatives.is_correct
        pagina_pdf INTEGER,       -- Página física no PDF (metadado auxiliar)
        image_path TEXT,          -- Nome do arquivo de imagem → questions.image_path
        review_status TEXT DEFAULT 'pending', -- 'pending'|'approved' → questions.review_status
        FOREIGN KEY (prova_id) REFERENCES provas (id)
    )''')
    
    # -----------------------------------------------------------------
    # TABELA: disciplinas
    # Espelha a tabela 'subjects' de produção.
    # -----------------------------------------------------------------
    cursor.execute('''CREATE TABLE IF NOT EXISTS disciplinas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE          -- → subjects.name
    )''')
    
    # -----------------------------------------------------------------
    # TABELA: questao_disciplina (pivot)
    # Espelha a tabela 'question_subject' de produção.
    # -----------------------------------------------------------------
    cursor.execute('''CREATE TABLE IF NOT EXISTS questao_disciplina (
        questao_id INTEGER,
        disciplina_id INTEGER,
        PRIMARY KEY (questao_id, disciplina_id),
        FOREIGN KEY (questao_id) REFERENCES questoes (id),
        FOREIGN KEY (disciplina_id) REFERENCES disciplinas (id)
    )''')
        
    conn.commit()
    return conn

# --- 4. SCRAPING E DOWNLOADS ---

def get_exam_links(url_banca, pagina):
    url = url_banca if pagina == 1 else f"{url_banca}/{pagina}"
    try:
        res = requests.get(url, headers=HEADERS)
        if res.status_code != 200: return None
        soup = BeautifulSoup(res.text, 'html.parser')
        table = soup.find('table', {'id': 'lista_provas'})
        if not table: return None
        
        links = [a['href'] for a in table.find_all('a', class_='prova_download')]
        return list(dict.fromkeys(links))
    except Exception as e:
        log(f"Erro ao buscar links: {e}")
        return None

def download_pdfs(url, banca_nome):
    try:
        res = requests.get(url, headers=HEADERS)
        soup = BeautifulSoup(res.text, 'html.parser')
        meta = {'cargo': 'N/A', 'ano': 'N/A', 'orgao': 'N/A', 'instituicao': 'N/A'}
        for li in soup.find_all('li', class_='mb-2'):
            t = li.get_text()
            if "Cargo:" in t: meta['cargo'] = li.find('a').text.strip() if li.find('a') else t.split("Cargo:")[-1].strip()
            elif "Ano:" in t: meta['ano'] = li.find('span').text.strip() if li.find('span') else t.split("Ano:")[-1].strip()
            elif "Órgão:" in t: meta['orgao'] = li.find('a').text.strip() if li.find('a') else t.split("Órgão:")[-1].strip()
            elif "Instituição:" in t: meta['instituicao'] = li.find('a').text.strip() if li.find('a') else t.split("Instituição:")[-1].strip()
        
        nome_pasta = f"{sanitize_name(meta['cargo'])}_{meta['ano']}"
        pasta_prova = os.path.join(BASE_DIR, banca_nome, "Provas", nome_pasta)
        
        if not os.path.exists(pasta_prova): 
            os.makedirs(pasta_prova)
        
        p_path, g_path = "", ""
        for card in soup.find_all('div', class_='card'):
            if "Download" in card.text:
                for link in card.find_all('a', class_='item-link'):
                    f_url = link.get('href')
                    if f_url and f_url.startswith('http'):
                        nome = f_url.split('/')[-1]
                        local = os.path.join(pasta_prova, nome)
                        if not os.path.exists(local) or os.path.getsize(local) == 0:
                            with open(local, 'wb') as f: f.write(requests.get(f_url, headers=HEADERS).content)
                        if "gabarito" in nome.lower(): g_path = local
                        else: p_path = local
        return meta, pasta_prova, p_path, g_path
    except Exception as e:
        log(f"Erro no download: {e}")
        return {}, "", "", ""

def recortar_area_da_pagina(pdf_path, pagina_fisica, coordenadas, output_path):
    try:
        doc = fitz.open(pdf_path)
        if pagina_fisica > len(doc): return False
        page = doc[pagina_fisica - 1]
        mat = fitz.Matrix(3, 3)
        pix = page.get_pixmap(matrix=mat)
        img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
        
        ymin, xmin, ymax, xmax = coordenadas
        width, height = img.size
        left, top = (xmin / 1000) * width, (ymin / 1000) * height
        right, bottom = (xmax / 1000) * width, (ymax / 1000) * height
        
        img.crop((left, top, right, bottom)).save(output_path, quality=95)
        return True
    except:
        return False

# --- 5. ANÁLISE IA ---

def analise_visual_ia(prova_path, gabarito_path, pasta_prova, prova_id, nome_cargo, banca):
    log(f"IA: Analisando prova com {SELECTED_MODEL_NAME}...")
    genai.configure(api_key=CURRENT_API_KEY)
    model = genai.GenerativeModel(SELECTED_MODEL_NAME)
    
    try:
        f_p = genai.upload_file(path=prova_path)
        f_g = genai.upload_file(path=gabarito_path)
        while f_p.state.name == "PROCESSING" or f_g.state.name == "PROCESSING":
            time.sleep(2)
            f_p = genai.get_file(f_p.name)
            f_g = genai.get_file(f_g.name)
    except Exception as e:
        log(f"Erro no upload para Gemini: {e}")
        return []

    prompt = """
    Extraia as questões da PROVA em JSON.
    ESTRUTURA:
    1. 'enunciado': Texto da pergunta.
    2. 'alternativas': Um objeto {"A": "...", "B": "..."}.
    3. 'gabarito': A letra correta.
    4. 'disciplinas': Uma LISTA de strings com as matérias/temas da questão (ex: ["Direito Administrativo"]).
    
    Se alternativas forem imagens, use "alternativas_visuais": true e "box_imagem": [ymin, xmin, ymax, xmax].
    
    Formato JSON esperado:
    [
      { "numero": "1", "enunciado": "...", "alternativas": {...}, "gabarito": "A", "disciplinas": ["Matéria"], "pagina": 1, "alternativas_visuais": false }
    ]
    """
    
    while True:
        try:
            res = model.generate_content([prompt, f_p, f_g])
            text = res.text.strip()
            if "```json" in text: text = text.split("```json")[1].split("```")[0].strip()
            elif "```" in text: text = text.split("```")[1].split("```")[0].strip()
            
            questoes = json.loads(text)
            
            pasta_imgs = os.path.join(pasta_prova, "imagens_questoes")
            if not os.path.exists(pasta_imgs): os.makedirs(pasta_imgs)
            
            for q in questoes:
                q['imagens_arquivos'] = ""
                if q.get('box_imagem'):
                    pag = int(q.get('pagina', 1))
                    box = q.get('box_imagem')
                    nome_arq = f"p{prova_id}_q{q['numero']}_img.jpg"
                    caminho_final = os.path.join(pasta_imgs, nome_arq)
                    if recortar_area_da_pagina(prova_path, pag, box, caminho_final):
                        q['imagens_arquivos'] = nome_arq
                
                if q.get('alternativas_visuais'):
                    registrar_revisao_manual(banca, prova_id, nome_cargo, q['numero'], "Alternativas são Visuais")
                        
            return questoes
            
        except Exception as e:
            error_msg = str(e).lower()
            if "429" in error_msg or "quota" in error_msg:
                wait_time = 65
                log(f"⛔ Quota excedida. Dormindo {wait_time}s...")
                time.sleep(wait_time)
                continue 
            else:
                log(f"❌ Erro fatal na extração JSON da IA: {e}")
                return []

# --- 6. GERAÇÃO DE ZIP PARA EXPORTAÇÃO ---

def gerar_zip_exportacao(nome_banca):
    """
    Gera um arquivo .zip contendo o banco de dados .db e todas as imagens.
    O arquivo sobrescreve qualquer exportação anterior da mesma banca.
    """
    banca_dir = os.path.join(BASE_DIR, nome_banca)
    if not os.path.exists(banca_dir):
        return
        
    nome_zip = f"exportacao_{nome_banca.lower()}.zip"
    caminho_zip = os.path.join(BASE_DIR, nome_zip)
    
    if os.path.exists(caminho_zip):
        os.remove(caminho_zip)
        
    log(f"\n📦 Gerando pacote final para o sistema: {nome_zip}...")
    
    with zipfile.ZipFile(caminho_zip, 'w', zipfile.ZIP_DEFLATED) as zipf:
        # 1. Adicionar o Banco de Dados na raiz do zip
        db_filename = f"banco_{nome_banca.lower()}.db"
        db_path = os.path.join(banca_dir, db_filename)
        if os.path.exists(db_path):
            zipf.write(db_path, arcname=db_filename)
            
        # 2. Varre a pasta da banca para encontrar as imagens recortadas
        contador_imagens = 0
        for root, dirs, files in os.walk(banca_dir):
            if "imagens_questoes" in root:
                for file in files:
                    if file.endswith(('.jpg', '.png', '.jpeg')):
                        caminho_arquivo = os.path.join(root, file)
                        # Salva todas as imagens dentro de uma pasta "imagens" no zip
                        zipf.write(caminho_arquivo, arcname=f"imagens/{file}")
                        contador_imagens += 1
                        
    log(f"✅ Pacote salvo em {caminho_zip} (Contém o .db e {contador_imagens} imagens).")

# --- 7. MAIN ---

def main():
    global SELECTED_MODEL_NAME, CURRENT_API_KEY, NOME_BANCA_ATUAL
    
    CURRENT_API_KEY = selecionar_api_key()
    SELECTED_MODEL_NAME = escolher_modelo_interativo()
    
    banca_selecionada = selecionar_banca()
    NOME_BANCA_ATUAL = banca_selecionada["nome"]
    url_banca = banca_selecionada["url"]
    
    conn = setup_db(NOME_BANCA_ATUAL)
    cursor = conn.cursor()
    
    pag_atual = obter_pagina_inicial(NOME_BANCA_ATUAL)
    log(f"=== INICIANDO SCRAPER | BANCA: {NOME_BANCA_ATUAL} | PÁGINA INICIAL: {pag_atual} ===")
    
    while True:
        links = get_exam_links(url_banca, pag_atual)
        if not links:
            log("Fim da raspagem desta banca (Sem mais páginas).")
            break
            
        for i, link in enumerate(links, 1):
            log(f"\n--- {NOME_BANCA_ATUAL} | PÁGINA {pag_atual} | PROVA {i}/{len(links)} ---")
            
            cursor.execute("SELECT id FROM provas WHERE source_url = ?", (link,))
            if cursor.fetchone():
                log("Prova já cadastrada no banco. Pulando...")
                continue
                
            meta, pasta_prova, p_path, g_path = download_pdfs(link, NOME_BANCA_ATUAL)
            
            if p_path and g_path:
                try:
                    data_extracao_atual = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    
                    # INSERT na tabela provas — colunas alinhadas com o schema de produção
                    cursor.execute('''INSERT INTO provas (organization, role, year, institution, origin, source_url, extracted_at)
                                      VALUES (?,?,?,?,?,?,?)''',
                                   (NOME_BANCA_ATUAL, meta['cargo'], meta['ano'], meta['orgao'], meta['instituicao'], link, data_extracao_atual))
                    
                    prova_id = cursor.lastrowid
                    
                    dados_questoes = analise_visual_ia(p_path, g_path, pasta_prova, prova_id, meta['cargo'], NOME_BANCA_ATUAL)
                    
                    if dados_questoes:
                        for q in dados_questoes:
                            # Serializa as alternativas como JSON para o campo 'alternativas'.
                            # O importador Laravel vai explodir isso para a tabela question_alternatives.
                            alts_json = json.dumps(q.get('alternativas'), ensure_ascii=False) if q.get('alternativas') else "{}"
                            
                            # Define o status de revisão:
                            # 'pending' → questão tem imagem ou alternativas visuais (precisa de crop pelo admin)
                            # 'approved' → questão puramente textual, pode ir direto para o banco público
                            status = 'pending' if (q.get('imagens_arquivos') or q.get('alternativas_visuais')) else 'approved'
                            
                            cursor.execute('''INSERT INTO questoes
                                (prova_id, numero, statement, alternativas, gabarito, pagina_pdf, image_path, review_status)
                                VALUES (?,?,?,?,?,?,?,?)''',
                                (prova_id, q.get('numero'), q.get('enunciado'), alts_json, q.get('gabarito'), q.get('pagina'), q.get('imagens_arquivos'), status))
                            
                            questao_id = cursor.lastrowid
                            
                            # Salva cada disciplina no formato de nome da produção (subjects.name)
                            disciplinas = q.get('disciplinas', [])
                            for disc_nome in disciplinas:
                                disc_nome = disc_nome.strip().upper()
                                
                                # Busca ou cria a disciplina usando a nova coluna 'name'
                                cursor.execute("SELECT id FROM disciplinas WHERE name = ?", (disc_nome,))
                                row = cursor.fetchone()
                                if row:
                                    disc_id = row[0]
                                else:
                                    cursor.execute("INSERT INTO disciplinas (name) VALUES (?)", (disc_nome,))
                                    disc_id = cursor.lastrowid
                                    
                                cursor.execute("INSERT OR IGNORE INTO questao_disciplina (questao_id, disciplina_id) VALUES (?,?)",
                                               (questao_id, disc_id))
                                               
                        conn.commit()
                        log(f"[OK] Prova ID {prova_id} salva e vinculada com sucesso!")
                    else:
                        conn.rollback()
                        log(f"[ERRO] IA não retornou dados. Desfazendo registro da prova.")
                
                except Exception as e:
                    conn.rollback()
                    log(f"[ERRO CRÍTICO] Falha ao gravar no banco: {e}")
            else:
                log(f"[AVISO] PDF da prova ou gabarito não encontrado para esta URL.")
                
            time.sleep(5)
            
        salvar_progresso(NOME_BANCA_ATUAL, pag_atual)
        pag_atual += 1

    # Aciona a criação do zip ao finalizar todas as páginas
    gerar_zip_exportacao(NOME_BANCA_ATUAL)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\n\n🛑 Interrompido pelo usuário. Encerrando de forma segura...")
        # Aciona a criação do zip caso o usuário pare o script manualmente (Ctrl+C)
        if NOME_BANCA_ATUAL:
            gerar_zip_exportacao(NOME_BANCA_ATUAL)
        sys.exit(0)