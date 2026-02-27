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
import zipfile
import signal 

# --- NOVA BIBLIOTECA DE CORES ---
# pip install colorama
from colorama import init, Fore, Style

# Inicializa o colorama para resetar a cor automaticamente ao final de cada print
init(autoreset=True)

# --- SUPRESSÃO DE AVISOS ---
warnings.filterwarnings("ignore")
import google.generativeai as genai

# --- CONFIGURAÇÕES GERAIS ---
BASE_DIR = "Dados_Scraper"
BANCAS_FILE = "bancas.txt"
API_KEY_FILE = "apikey.txt"
API_PRICES_FILE = "api_prices.txt"
ESTADO_SCRAPING_FILE = os.path.join(BASE_DIR, "estado_scraping.json")

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}

SELECTED_MODEL_NAME = ''
ORGANIZER_MODEL_NAME = ''
CURRENT_API_KEY = ''
NOME_BANCA_ATUAL = None

# Variáveis globais para rastreamento de custos
TOTAL_PROMPT_TOKENS = 0
TOTAL_COMPLETION_TOKENS = 0
TOTAL_COST_USD = 0.0
PRECOS_MODELOS = {}

# Variáveis globais para rotação de API Keys
CHAVES_DISPONIVEIS = []
INDICE_CHAVE_ATUAL = 0

# --- FUNÇÕES DE INICIALIZAÇÃO E UTILIDADES ---

def log(mensagem, cor=Fore.WHITE):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"{cor}[{timestamp}] {mensagem}")

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
        log(f"⚠️ Arquivo '{BANCAS_FILE}' criado com exemplos. Edite-o se necessário.", Fore.YELLOW)
        
    if not os.path.exists(API_PRICES_FILE):
        with open(API_PRICES_FILE, "w", encoding="utf-8") as f:
            f.write("# Modelo, Preço Input (1M), Preço Output (1M)\n")
            f.write("models/gemini-1.5-flash,0.075,0.30\n")
            f.write("models/gemini-1.5-pro,1.25,5.00\n")
        log(f"⚠️ Arquivo '{API_PRICES_FILE}' criado com preços base. Atualize-o.", Fore.YELLOW)

def carregar_precos_api():
    precos = {}
    if os.path.exists(API_PRICES_FILE):
        with open(API_PRICES_FILE, "r", encoding="utf-8") as f:
            for linha in f:
                if not linha.strip() or linha.strip().startswith('#'): continue
                partes = linha.strip().split(',')
                if len(partes) >= 3:
                    modelo = partes[0].strip()
                    try:
                        preco_in = float(partes[1].strip())
                        preco_out = float(partes[2].strip())
                        precos[modelo] = {'input': preco_in, 'output': preco_out}
                    except ValueError:
                        continue
    return precos

def exibir_resumo_custos():
    print(Fore.CYAN + "\n" + "="*50)
    print(Fore.CYAN + "📊 RESUMO DE CONSUMO E CUSTOS DA SESSÃO")
    print(Fore.CYAN + "="*50)
    print(Fore.LIGHTBLUE_EX + f"🔹 Tokens de Input (Prompt): {TOTAL_PROMPT_TOKENS:,}")
    print(Fore.LIGHTBLUE_EX + f"🔹 Tokens de Output (Resposta): {TOTAL_COMPLETION_TOKENS:,}")
    print(Fore.GREEN + f"💵 CUSTO TOTAL ESTIMADO: U$ {TOTAL_COST_USD:.6f}")
    print(Fore.CYAN + "="*50 + "\n")

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
        print(Fore.RED + f"❌ Nenhuma banca configurada no '{BANCAS_FILE}'.")
        sys.exit(1)
        
    print(Fore.MAGENTA + "\n" + "="*50)
    print(Fore.MAGENTA + "📚 SELEÇÃO DE BANCA PARA SCRAPING")
    print(Fore.MAGENTA + "="*50)
    for i, b in enumerate(bancas, 1):
        print(Fore.WHITE + f"[{i}] {b['nome']} ({b['url']})")
        
    while True:
        try:
            escolha = input(Fore.CYAN + "\n👉 Digite o NÚMERO da banca: " + Style.RESET_ALL)
            idx = int(escolha) - 1
            if 0 <= idx < len(bancas):
                return bancas[idx]
            print(Fore.RED + "❌ Número inválido.")
        except ValueError:
            print(Fore.RED + "❌ Digite apenas o número.")

# --- 1. GESTÃO DE API KEYS E MODELOS ---

def carregar_chaves_do_arquivo():
    if not os.path.exists(API_KEY_FILE):
        print(Fore.RED + f"\n❌ Arquivo '{API_KEY_FILE}' não encontrado!")
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
        print(Fore.RED + f"\n❌ Nenhuma chave válida encontrada.")
        sys.exit(1)
        
    return chaves_encontradas

def selecionar_api_key():
    global CHAVES_DISPONIVEIS, INDICE_CHAVE_ATUAL
    
    print(Fore.YELLOW + "\n" + "="*50)
    print(Fore.YELLOW + "🔑 SELEÇÃO DE CONTA INICIAL (API KEY)")
    print(Fore.YELLOW + "="*50)
    
    CHAVES_DISPONIVEIS = carregar_chaves_do_arquivo()
    if len(CHAVES_DISPONIVEIS) == 1:
        print(Fore.CYAN + f"\nℹ️ Apenas uma chave encontrada: {CHAVES_DISPONIVEIS[0]['apelido']}")
        INDICE_CHAVE_ATUAL = 0
        genai.configure(api_key=CHAVES_DISPONIVEIS[0]['key'])
        return CHAVES_DISPONIVEIS[0]['key']

    for i, item in enumerate(CHAVES_DISPONIVEIS, 1):
        chave_mascarada = f"...{item['key'][-6:]}"
        print(Fore.WHITE + f"[{i}] {item['apelido']} \t({chave_mascarada})")
    
    while True:
        try:
            escolha = input(Fore.CYAN + "\n👉 Digite o NÚMERO da chave para iniciar: " + Style.RESET_ALL)
            idx = int(escolha) - 1
            if 0 <= idx < len(CHAVES_DISPONIVEIS):
                INDICE_CHAVE_ATUAL = idx
                escolhida = CHAVES_DISPONIVEIS[idx]
                print(Fore.GREEN + f"\n✅ CONTA INICIAL SELECIONADA: {escolhida['apelido']}")
                genai.configure(api_key=escolhida['key'])
                return escolhida['key']
        except ValueError:
            pass
        print(Fore.RED + "❌ Inválido.")

def rotacionar_api_key():
    global INDICE_CHAVE_ATUAL, CURRENT_API_KEY, CHAVES_DISPONIVEIS
    if len(CHAVES_DISPONIVEIS) <= 1:
        log("⚠️ Limite atingido, mas só há UMA chave configurada. Aguardando 65s para reset...", Fore.YELLOW)
        time.sleep(65)
        return

    INDICE_CHAVE_ATUAL = (INDICE_CHAVE_ATUAL + 1) % len(CHAVES_DISPONIVEIS)
    nova_chave = CHAVES_DISPONIVEIS[INDICE_CHAVE_ATUAL]
    
    CURRENT_API_KEY = nova_chave['key']
    genai.configure(api_key=CURRENT_API_KEY)
    
    log(f"🔄 COTA EXCEDIDA (429)! Rotacionando automaticamente para a chave: [{nova_chave['apelido']}]...", Fore.YELLOW)
    
    if INDICE_CHAVE_ATUAL == 0:
        log("⏳ Todas as chaves foram tentadas e esgotaram a cota. Pausando 65 segundos para o reset do Google...", Fore.LIGHTRED_EX)
        time.sleep(65)
    else:
        time.sleep(2)

def escolher_modelo_interativo(mensagem_titulo, sugerir_flash=False):
    print(Fore.CYAN + "\n" + "="*50)
    print(Fore.CYAN + f"🤖 {mensagem_titulo}")
    if sugerir_flash:
        print(Fore.YELLOW + "💡 Sugestão: Use o 'gemini-1.5-flash' ou 'gemini-2.0-flash' pois são baratos e ideais para essa tarefa rápida.")
    print(Fore.CYAN + "="*50)
    
    try:
        modelos_disponiveis = [m for m in genai.list_models() if 'generateContent' in m.supported_generation_methods]
        modelos_disponiveis.sort(key=lambda x: x.name)

        for i, model in enumerate(modelos_disponiveis, 1):
            nome_display = model.name.replace('models/', '')
            print(Fore.WHITE + f"[{i}] {nome_display} \t({model.display_name})")

        while True:
            try:
                escolha = input(Fore.CYAN + "\n👉 Digite o NÚMERO do modelo desejado: " + Style.RESET_ALL)
                idx = int(escolha) - 1
                if 0 <= idx < len(modelos_disponiveis):
                    modelo_candidato = modelos_disponiveis[idx].name
                    print(Fore.GREEN + f"🚀 Usando: {modelo_candidato}")
                    return modelo_candidato
            except ValueError:
                pass
            print(Fore.RED + "❌ Inválido.")
    except Exception as e:
        print(Fore.RED + f"❌ Erro ao listar modelos: {e}. Usando fallback.")
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

def registrar_revisao_manual(banca, exam_id, cargo, numero_questao, motivo):
    arquivo_revisao = os.path.join(BASE_DIR, banca, f"revisao_{banca.lower()}.txt")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    linha = f"[{timestamp}] EXAM ID: {exam_id} | CARGO: {cargo} | QUESTÃO: {numero_questao} | MOTIVO: {motivo}\n"
    with open(arquivo_revisao, "a", encoding="utf-8") as f:
        f.write(linha)

# --- 3. BANCO DE DADOS RELACIONAL ---

def setup_db(banca_nome):
    banca_dir = os.path.join(BASE_DIR, banca_nome)
    if not os.path.exists(banca_dir):
        os.makedirs(banca_dir)
        
    db_path = os.path.join(banca_dir, f"banco_{banca_nome.lower()}.db")
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS exams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization TEXT,
        role TEXT,
        year TEXT,
        institution TEXT,
        origin TEXT,
        source_url TEXT UNIQUE,
        extracted_at TEXT
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        exam_id INTEGER,
        number TEXT,
        statement TEXT,
        alternatives TEXT,
        correct_answer TEXT,
        pdf_page INTEGER,
        image_path TEXT,
        review_status TEXT DEFAULT 'pending',
        FOREIGN KEY (exam_id) REFERENCES exams (id)
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS question_subject (
        question_id INTEGER,
        subject_id INTEGER,
        PRIMARY KEY (question_id, subject_id),
        FOREIGN KEY (question_id) REFERENCES questions (id),
        FOREIGN KEY (subject_id) REFERENCES subjects (id)
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS question_topic (
        question_id INTEGER,
        topic_id INTEGER,
        PRIMARY KEY (question_id, topic_id),
        FOREIGN KEY (question_id) REFERENCES questions (id),
        FOREIGN KEY (topic_id) REFERENCES topics (id)
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS failed_scrapes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT UNIQUE,
        error_message TEXT,
        attempted_at TEXT
    )''')
        
    conn.commit()
    return conn

def registrar_falha_no_banco(conn, url, motivo):
    try:
        cursor = conn.cursor()
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("INSERT OR REPLACE INTO failed_scrapes (url, error_message, attempted_at) VALUES (?,?,?)", 
                       (url, motivo, timestamp))
        conn.commit()
    except Exception as e:
        log(f"Erro ao registrar falha no log interno: {e}", Fore.RED)

# --- 4. SCRAPING, DOWNLOADS E ORGANIZADOR IA ---

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
        log(f"Erro ao buscar links: {e}", Fore.RED)
        return None

def organizar_arquivos_com_ia(lista_arquivos, cargo, banca):
    global TOTAL_PROMPT_TOKENS, TOTAL_COMPLETION_TOKENS, TOTAL_COST_USD, PRECOS_MODELOS
    
    genai.configure(api_key=CURRENT_API_KEY)
    model = genai.GenerativeModel(ORGANIZER_MODEL_NAME)
    
    prompt_organizador = f"""
    Você é um classificador especializado em arquivos de concursos públicos.
    Cargo alvo: {cargo}
    Banca: {banca}
    Arquivos encontrados na página de download: {lista_arquivos}

    Sua tarefa:
    1. Analise os nomes dos arquivos.
    2. Identifique quais são os Cadernos de Prova Objetiva e qual é o Gabarito.
    3. Agrupe-os. É comum que um único gabarito sirva para múltiplas provas. Neste caso, agrupe todos os cadernos de prova junto com esse gabarito.

    Retorne APENAS um JSON válido, sem formatação markdown (sem ```json), neste formato estrito:
    [
      {{
        "identificador": "Nome descritivo curto do cargo ou bloco",
        "cadernos_de_prova": ["arquivo_prova1.pdf", "arquivo_prova2.pdf"],
        "gabarito_correspondente": "arquivo_gabarito.pdf"
      }}
    ]
    * A chave 'cadernos_de_prova' DEVE ser uma lista de strings.
    * A chave 'gabarito_correspondente' DEVE ser uma string única.
    * Se não houver prova e gabarito para formar um par/grupo válido, retorne [].
    """
    
    while True:
        try:
            res = model.generate_content(prompt_organizador)
            
            try:
                p_tokens = res.usage_metadata.prompt_token_count
                c_tokens = res.usage_metadata.candidates_token_count
            except AttributeError:
                p_tokens, c_tokens = 0, 0
                
            custo_org = 0.0
            modelo_limpo = ORGANIZER_MODEL_NAME.replace("models/", "")
            
            modelo_busca = f"{modelo_limpo}<=200k" if f"{modelo_limpo}<=200k" in PRECOS_MODELOS else modelo_limpo
                
            if modelo_busca in PRECOS_MODELOS:
                preco_in = PRECOS_MODELOS[modelo_busca]['input']
                preco_out = PRECOS_MODELOS[modelo_busca]['output']
                custo_org = (p_tokens / 1_000_000) * preco_in + (c_tokens / 1_000_000) * preco_out
            
            TOTAL_PROMPT_TOKENS += p_tokens
            TOTAL_COMPLETION_TOKENS += c_tokens
            TOTAL_COST_USD += custo_org
            
            texto_json = res.text.strip()
            if "```json" in texto_json: texto_json = texto_json.split("```json")[1].split("```")[0].strip()
            elif "```" in texto_json: texto_json = texto_json.split("```")[1].split("```")[0].strip()
            
            grupos = json.loads(texto_json)
            return grupos
            
        except Exception as e:
            error_msg = str(e).lower()
            if "429" in error_msg or "quota" in error_msg:
                rotacionar_api_key()
                model = genai.GenerativeModel(ORGANIZER_MODEL_NAME)
                continue
            else:
                log(f"   [Filtro IA] Erro ao tentar classificar os arquivos com IA: {e}", Fore.RED)
                return []

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
        
        arquivos_baixados = []
        for card in soup.find_all('div', class_='card'):
            if "Download" in card.text:
                for link in card.find_all('a', class_='item-link'):
                    f_url = link.get('href')
                    if f_url and f_url.startswith('http'):
                        nome = f_url.split('/')[-1]
                        local = os.path.join(pasta_prova, nome)
                        if not os.path.exists(local) or os.path.getsize(local) == 0:
                            with open(local, 'wb') as f: f.write(requests.get(f_url, headers=HEADERS).content)
                        arquivos_baixados.append(nome)

        arquivos_validos = []
        for nome in arquivos_baixados:
            nome_min = nome.lower()
            if any(x in nome_min for x in ['discursiva', 'pratica', 'redacao', 'titulos', 'edital']):
                continue
            arquivos_validos.append(nome)
            
        if len(arquivos_validos) == 2:
            file1 = arquivos_validos[0]
            file2 = arquivos_validos[1]
            
            is_gabarito1 = "gabarito" in file1.lower() or "resp" in file1.lower()
            is_gabarito2 = "gabarito" in file2.lower() or "resp" in file2.lower()
            
            gabarito_file = None
            prova_file = None
            
            if is_gabarito1 and not is_gabarito2:
                gabarito_file = file1
                prova_file = file2
            elif is_gabarito2 and not is_gabarito1:
                gabarito_file = file2
                prova_file = file1
                
            if gabarito_file and prova_file:
                log("   [Atalho] Par óbvio detectado (1 Prova + 1 Gabarito). Ignorando IA Organizadora.", Fore.GREEN)
                pares_validos = [{
                    'identificador': 'Geral',
                    'provas': [os.path.join(pasta_prova, prova_file)],
                    'gabarito': os.path.join(pasta_prova, gabarito_file)
                }]
                return meta, pasta_prova, pares_validos

        grupos_organizados = []
        if arquivos_validos:
            log(f"   [Organizador] Cenário complexo. Chamando classificador {ORGANIZER_MODEL_NAME}...", Fore.YELLOW)
            grupos_organizados = organizar_arquivos_com_ia(arquivos_validos, meta['cargo'], banca_nome)
        
        pares_validos = []
        for grupo in grupos_organizados:
            gabarito = grupo.get('gabarito_correspondente', '')
            provas = grupo.get('cadernos_de_prova', [])
            identificador = grupo.get('identificador', 'Geral')
            
            if gabarito in arquivos_validos and all(p in arquivos_validos for p in provas):
                pares_validos.append({
                    'identificador': identificador,
                    'provas': [os.path.join(pasta_prova, p) for p in provas],
                    'gabarito': os.path.join(pasta_prova, gabarito)
                })
            else:
                log(f"   [Aviso] Grupo '{identificador}' formado pela IA contém arquivos faltantes/descartados.", Fore.YELLOW)

        return meta, pasta_prova, pares_validos
        
    except Exception as e:
        log(f"Erro no download ou organização: {e}", Fore.RED)
        return {}, "", []

def recortar_area_da_pagina(pdf_path, pagina_fisica, coordenadas, output_path):
    try:
        doc = fitz.open(pdf_path)
        if pagina_fisica < 1 or pagina_fisica > len(doc): 
            log(f"Erro: Página {pagina_fisica} inexistente no PDF para recorte.", Fore.RED)
            return False
            
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
    except Exception as e:
        log(f"Erro ao tentar recortar área do PDF na página {pagina_fisica}: {e}", Fore.RED)
        return False

# --- 5. ANÁLISE IA ---

def analise_visual_ia(provas_paths, gabarito_path, pasta_prova, exam_id, nome_cargo, banca):
    global TOTAL_PROMPT_TOKENS, TOTAL_COMPLETION_TOKENS, TOTAL_COST_USD, PRECOS_MODELOS
    
    log(f"IA: Enviando para Extrator {SELECTED_MODEL_NAME} (Cadernos: {len(provas_paths)} | Gabarito: 1)...", Fore.CYAN)
    genai.configure(api_key=CURRENT_API_KEY)
    model = genai.GenerativeModel(SELECTED_MODEL_NAME)
    
    try:
        arquivos_enviados_provas = []
        for p_path in provas_paths:
            f = genai.upload_file(path=p_path)
            arquivos_enviados_provas.append(f)
            
        f_g = genai.upload_file(path=gabarito_path)
        
        todos_ativos = False
        while not todos_ativos:
            todos_ativos = True
            for i, f_p in enumerate(arquivos_enviados_provas):
                arquivos_enviados_provas[i] = genai.get_file(f_p.name)
                if arquivos_enviados_provas[i].state.name == "PROCESSING":
                    todos_ativos = False
                    
            f_g = genai.get_file(f_g.name)
            if f_g.state.name == "PROCESSING":
                todos_ativos = False
                
            if not todos_ativos:
                time.sleep(2)
                
    except Exception as e:
        log(f"Erro no upload para Gemini: {e}", Fore.RED)
        return []

    # --- NOVO PROMPT: Suporte para MÚLTIPLAS imagens por questão ---
    # --- NOVO PROMPT: BLINDADO PARA IMAGENS E ANTI-PREGUIÇA ---
    prompt = """
    Extraia as questões das PROVAS em JSON. Considere todos os cadernos de prova fornecidos em conjunto com o gabarito.
    
    🚨 REGRA DE OURO (ANTI-PREGUIÇA): É ESTRITAMENTE PROIBIDO parar pela metade, resumir ou omitir questões. Você DEVE extrair ABSOLUTAMENTE TODAS AS QUESTÕES da prova, da número 1 até a ÚLTIMA (mesmo que a prova tenha 80, 100 ou mais questões). Leia o documento até a última página e não abrevie o JSON!

    ESTRUTURA:
    1. 'enunciado': Texto da pergunta.
    2. 'alternativas': Um objeto com as opções. Ex: {"A": "...", "B": "..."}. 
       - ATENÇÃO: Se for questão de CERTO/ERRADO (comum no Cebraspe), padronize obrigatoriamente as alternativas como: {"C": "Certo", "E": "Errado"} e use a respectiva letra no campo 'gabarito'.
    3. 'gabarito': A letra correta.
    4. 'disciplinas': Uma LISTA de strings com as matérias gerais (ex: ["Português", "Matemática"]).
    5. 'assuntos': Uma LISTA de strings com tópicos específicos (ex: ["Gramática", "Geometria"]).
    
    ATENÇÃO EXTREMA A IMAGENS, GRÁFICOS, TABELAS E FÓRMULAS:
    Você tem falhado em detectar figuras que são esquemas desenhados (ex: blocos, circuitos, trens, diagramas de força). Siga estas regras rigorosamente:
    1. GATILHOS OBRIGATÓRIOS: Se o texto mencionar "gráfico", "figura a seguir", "tabela", "charge", "esquema", "imagem", ou "ilustra", VOCÊ É OBRIGADO a definir "contem_imagem": true e capturar a área dessa figura, MESMO QUE ELA SEJA FEITA APENAS DE LINHAS BRANCAS E PRETAS no meio do texto.
    2. REGRA DO BLOCO DE ALTERNATIVAS: Se as alternativas (A, B, C, D) contiverem FÓRMULAS FÍSICAS/MATEMÁTICAS complexas (vetores, módulos, raízes) ou gráficos, NÃO TENTE TRANSCREVER OU RECORTAR LETRA POR LETRA. Você deve desenhar UMA ÚNICA CAIXA gigante englobando todo o bloco de alternativas juntas.
    3. MÚLTIPLAS IMAGENS: Se a questão for como uma de Física (com um desenho de um trem/bloco no enunciado E fórmulas complexas nas alternativas), você deve retornar DUAS caixas na lista "boxes_imagens": uma caixa englobando o desenho do trem, e outra caixa gigante englobando o bloco de alternativas.
    4. MARGEM DE SEGURANÇA: Expanda os limites das caixas [ymin, xmin, ymax, xmax] em 5% para fora, para não cortar as pontas dos desenhos ou eixos.
    
    Se houver elemento visual, retorne uma LISTA DE COORDENADAS na chave "boxes_imagens". Ex: [[ymin, xmin, ymax, xmax]] (escala 0 a 1000).
    Se NÃO houver imagens, defina "contem_imagem": false e retorne "boxes_imagens": [].

    ATENÇÃO À SINTAXE JSON: Você está gerando um arquivo muito grande. É estritamente proibido deixar vírgulas sobrando no final de objetos ou listas (trailing commas). O JSON deve ser perfeitamente válido.
    Formato JSON esperado:
    [
      { 
        "numero": "1", 
        "enunciado": "...", 
        "alternativas": {...}, 
        "gabarito": "A", 
        "disciplinas": ["Matéria Geral"], 
        "assuntos": ["Tópico Específico"],
        "pagina": 1, 
        "contem_imagem": false,
        "boxes_imagens": []
      }
    ]
    """
    
    while True:
        try:
            conteudo_requisicao = [prompt] + arquivos_enviados_provas + [f_g]

            # --- NOVO: Trava nativa do Google para forçar um JSON 100% sem erros de sintaxe ---
            res = model.generate_content(
                conteudo_requisicao,
                generation_config={"response_mime_type": "application/json"}
            )
            
            try:
                p_tokens = res.usage_metadata.prompt_token_count
                c_tokens = res.usage_metadata.candidates_token_count
            except AttributeError:
                p_tokens = 0
                c_tokens = 0
                
            custo_prova = 0.0
            
            modelo_limpo = SELECTED_MODEL_NAME.replace("models/", "")
            total_tokens = p_tokens + c_tokens
            modelo_busca = modelo_limpo
            
            if total_tokens > 200000 and f"{modelo_limpo}>200k" in PRECOS_MODELOS:
                modelo_busca = f"{modelo_limpo}>200k"
            elif total_tokens <= 200000 and f"{modelo_limpo}<=200k" in PRECOS_MODELOS:
                modelo_busca = f"{modelo_limpo}<=200k"

            if modelo_busca in PRECOS_MODELOS:
                preco_in = PRECOS_MODELOS[modelo_busca]['input']
                preco_out = PRECOS_MODELOS[modelo_busca]['output']
                custo_prova = (p_tokens / 1_000_000) * preco_in + (c_tokens / 1_000_000) * preco_out
            
            TOTAL_PROMPT_TOKENS += p_tokens
            TOTAL_COMPLETION_TOKENS += c_tokens
            TOTAL_COST_USD += custo_prova
            
            log(f"🪙  Tokens usados (Extrator): {p_tokens} in | {c_tokens} out", Fore.BLUE)
            log(f"💵 Custo desta extração: U$ {custo_prova:.6f}", Fore.BLUE)

            text = res.text.strip()
            if "```json" in text: text = text.split("```json")[1].split("```")[0].strip()
            elif "```" in text: text = text.split("```")[1].split("```")[0].strip()
            
            questoes = json.loads(text)
            
            pasta_imgs = os.path.join(pasta_prova, "imagens_questoes")
            if not os.path.exists(pasta_imgs): os.makedirs(pasta_imgs)
            
            # --- NOVO: Loop de extração para suportar infinitas imagens por questão ---
            for q in questoes:
                q['imagens_arquivos'] = ""
                arquivos_recortados = [] # Lista para guardar os nomes das imagens desta questão
                
                if q.get('contem_imagem') and q.get('boxes_imagens'):
                    pag = int(q.get('pagina', 1))
                    boxes = q.get('boxes_imagens', [])
                    
                    # Se a IA acidentalmente retornar uma única lista [ymin, xmin, ymax, xmax] em vez de matriz, ajusta.
                    if len(boxes) == 4 and isinstance(boxes[0], (int, float)):
                        boxes = [boxes]
                    
                    for idx, box in enumerate(boxes):
                        if isinstance(box, list) and len(box) == 4 and box != [0,0,0,0]:
                            nome_arq = f"e{exam_id}_q{q['numero']}_img_{idx+1}.jpg"
                            caminho_final = os.path.join(pasta_imgs, nome_arq)
                            if recortar_area_da_pagina(provas_paths[0], pag, box, caminho_final):
                                arquivos_recortados.append(nome_arq)
                
                if arquivos_recortados:
                    # Salva os nomes separados por vírgula no banco de dados (Ex: "img_1.jpg, img_2.jpg")
                    q['imagens_arquivos'] = ",".join(arquivos_recortados)
                    registrar_revisao_manual(banca, exam_id, nome_cargo, q['numero'], "Questão contém Imagens/Figuras")
            # --------------------------------------------------------------------------
                        
            return questoes
            
        except Exception as e:
            error_msg = str(e).lower()
            if "429" in error_msg or "quota" in error_msg:
                rotacionar_api_key()
                model = genai.GenerativeModel(SELECTED_MODEL_NAME)
                continue 
            else:
                log(f"❌ Erro fatal na extração JSON da IA: {e}", Fore.RED)
                return []

# --- 6. GERAÇÃO DE ZIP PARA EXPORTAÇÃO ---

def gerar_zip_exportacao(nome_banca):
    banca_dir = os.path.join(BASE_DIR, nome_banca)
    if not os.path.exists(banca_dir):
        return
        
    nome_zip = f"exportacao_{nome_banca.lower()}.zip"
    caminho_zip = os.path.join(BASE_DIR, nome_zip)
    
    if os.path.exists(caminho_zip):
        os.remove(caminho_zip)
        
    log(f"\n📦 Gerando pacote final para o sistema: {nome_zip}...", Fore.CYAN)
    
    with zipfile.ZipFile(caminho_zip, 'w', zipfile.ZIP_DEFLATED) as zipf:
        db_filename = f"banco_{nome_banca.lower()}.db"
        db_path = os.path.join(banca_dir, db_filename)
        if os.path.exists(db_path):
            zipf.write(db_path, arcname=db_filename)
            
        contador_imagens = 0
        for root, dirs, files in os.walk(banca_dir):
            if "imagens_questoes" in root:
                for file in files:
                    if file.endswith(('.jpg', '.png', '.jpeg')):
                        caminho_arquivo = os.path.join(root, file)
                        zipf.write(caminho_arquivo, arcname=f"imagens/{file}")
                        contador_imagens += 1
                        
    log(f"✅ Pacote salvo em {caminho_zip} (Contém o .db e {contador_imagens} imagens).", Fore.GREEN)

# --- 7. MAIN ---

def main():
    global SELECTED_MODEL_NAME, ORGANIZER_MODEL_NAME, CURRENT_API_KEY, NOME_BANCA_ATUAL, PRECOS_MODELOS
    
    PRECOS_MODELOS = carregar_precos_api()
    CURRENT_API_KEY = selecionar_api_key()
    
    SELECTED_MODEL_NAME = escolher_modelo_interativo("ESCOLHA O MODELO EXTRATOR PRINCIPAL (Para ler questões da prova)")
    ORGANIZER_MODEL_NAME = escolher_modelo_interativo("ESCOLHA O MODELO CLASSIFICADOR/ORGANIZADOR (Para parear múltiplos PDFs)", sugerir_flash=True)

    banca_selecionada = selecionar_banca()
    NOME_BANCA_ATUAL = banca_selecionada["nome"]
    url_banca = banca_selecionada["url"]
    
    print(Fore.CYAN + "\n" + "="*50)
    entrada_limite = input(Fore.CYAN + "👉 Deseja extrair quantas provas nesta sessão? (Deixe em branco para extrair tudo): " + Style.RESET_ALL).strip()
    limite_provas = int(entrada_limite) if entrada_limite.isdigit() else None
    provas_processadas_sessao = 0
    atingiu_limite_global = False
    
    conn = setup_db(NOME_BANCA_ATUAL)
    cursor = conn.cursor()
    
    pag_atual = obter_pagina_inicial(NOME_BANCA_ATUAL)
    log(f"=== INICIANDO SCRAPER | BANCA: {NOME_BANCA_ATUAL} | PÁGINA INICIAL: {pag_atual} ===", Fore.MAGENTA)
    
    while True:
        if atingiu_limite_global:
            break
            
        links = get_exam_links(url_banca, pag_atual)
        if not links:
            log("Fim da raspagem desta banca (Sem mais páginas).", Fore.CYAN)
            break
            
        for i, link in enumerate(links, 1):
            if atingiu_limite_global:
                break
                
            log(f"\n--- {NOME_BANCA_ATUAL} | PÁGINA {pag_atual} | LINK {i}/{len(links)} ---", Fore.MAGENTA)
            
            meta, pasta_prova, grupos_validos = download_pdfs(link, NOME_BANCA_ATUAL)
            
            if not grupos_validos:
                conn = setup_db(NOME_BANCA_ATUAL)
                registrar_falha_no_banco(conn, link, "Nenhum grupo válido encontrado (Ignorado pelo Bypass ou Falha no Organizador).")
                log(f"[AVISO] Não foi possível agrupar cadernos/gabaritos válidos nesta URL.", Fore.YELLOW)
                time.sleep(2)
                continue
                
            for grupo in grupos_validos:
                
                if limite_provas and provas_processadas_sessao >= limite_provas:
                    log(f"🎯 Limite escolhido pelo usuário ({limite_provas} provas) foi atingido.", Fore.GREEN)
                    atingiu_limite_global = True
                    break
                    
                provas_paths = grupo['provas']
                g_path = grupo['gabarito']
                identificador = sanitize_name(grupo['identificador'])
                
                cargo_final = meta['cargo']
                link_virtual = link
                if identificador and identificador.lower() != 'geral':
                    cargo_final = f"{meta['cargo']} - {identificador.upper()}"
                    link_virtual = f"{link}#{identificador.replace(' ', '')}"
                    
                cursor.execute("SELECT id FROM exams WHERE source_url = ?", (link_virtual,))
                if cursor.fetchone():
                    log(f"Prova já cadastrada no banco: {cargo_final}. Pulando...", Fore.YELLOW)
                    continue
                    
                arquivos_nomes = [os.path.basename(p) for p in provas_paths]
                log(f"  -> Processando Grupo: {arquivos_nomes} + {os.path.basename(g_path)}", Fore.CYAN)
                
                try:
                    data_extracao_atual = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    
                    cursor.execute('''INSERT INTO exams (organization, role, year, institution, origin, source_url, extracted_at)
                                      VALUES (?,?,?,?,?,?,?)''',
                                   (NOME_BANCA_ATUAL, cargo_final, meta['ano'], meta['orgao'], meta['instituicao'], link_virtual, data_extracao_atual))
                    
                    exam_id = cursor.lastrowid
                    
                    dados_questoes = analise_visual_ia(provas_paths, g_path, pasta_prova, exam_id, cargo_final, NOME_BANCA_ATUAL)
                    
                    if dados_questoes:
                        total_encontradas = len(dados_questoes)
                        questoes_salvas = 0
                        
                        for q in dados_questoes:
                            alts_json = json.dumps(q.get('alternativas'), ensure_ascii=False) if q.get('alternativas') else "{}"
                            status = 'review' if (q.get('imagens_arquivos') or q.get('alternativas_visuais')) else 'approved'
                            
                            cursor.execute('''INSERT INTO questions
                                (exam_id, number, statement, alternatives, correct_answer, pdf_page, image_path, review_status)
                                VALUES (?,?,?,?,?,?,?,?)''',
                                (exam_id, q.get('numero'), q.get('enunciado'), alts_json, q.get('gabarito'), q.get('pagina'), q.get('imagens_arquivos'), status))
                            
                            question_id = cursor.lastrowid
                            
                            disciplinas = q.get('disciplinas', [])
                            for disc_nome in disciplinas:
                                disc_nome = str(disc_nome).strip().upper()
                                cursor.execute("SELECT id FROM subjects WHERE name = ?", (disc_nome,))
                                row = cursor.fetchone()
                                if row: disc_id = row[0]
                                else:
                                    cursor.execute("INSERT INTO subjects (name) VALUES (?)", (disc_nome,))
                                    disc_id = cursor.lastrowid
                                cursor.execute("INSERT OR IGNORE INTO question_subject (question_id, subject_id) VALUES (?,?)",
                                               (question_id, disc_id))

                            assuntos = q.get('assuntos', [])
                            for ass_nome in assuntos:
                                ass_nome = str(ass_nome).strip().upper()
                                cursor.execute("SELECT id FROM topics WHERE name = ?", (ass_nome,))
                                row_ass = cursor.fetchone()
                                if row_ass:
                                    ass_id = row_ass[0]
                                else:
                                    cursor.execute("INSERT INTO topics (name) VALUES (?)", (ass_nome,))
                                    ass_id = cursor.lastrowid
                                cursor.execute("INSERT OR IGNORE INTO question_topic (question_id, topic_id) VALUES (?,?)",
                                               (question_id, ass_id))
                                               
                            questoes_salvas += 1
                                               
                        conn.commit()
                        cursor.execute("DELETE FROM failed_scrapes WHERE url = ?", (link_virtual,))
                        conn.commit()
                        
                        log(f"[OK] Prova ID {exam_id} salva e vinculada com sucesso!", Fore.GREEN)
                        
                        # --- NOVOS LOGS EM CINZA ---
                        log(f"   ↳ Questões encontradas pela IA: {total_encontradas}", Fore.LIGHTBLACK_EX)
                        log(f"   ↳ Questões salvas no BD: {questoes_salvas}", Fore.LIGHTBLACK_EX)
                        # ---------------------------
                        
                    else:
                        conn.rollback()
                        registrar_falha_no_banco(conn, link_virtual, "IA não retornou dados (Erro de análise ou JSON)")
                        log(f"[ERRO] IA principal não retornou dados.", Fore.RED)
                
                except Exception as e:
                    conn.rollback()
                    registrar_falha_no_banco(conn, link_virtual, f"Erro crítico de banco: {str(e)}")
                    log(f"[ERRO CRÍTICO] Falha ao gravar no banco: {e}", Fore.LIGHTRED_EX)
                    
                provas_processadas_sessao += 1
                time.sleep(5)
            
        if not atingiu_limite_global:
            salvar_progresso(NOME_BANCA_ATUAL, pag_atual)
            pag_atual += 1

    exibir_resumo_custos()
    gerar_zip_exportacao(NOME_BANCA_ATUAL)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        signal.signal(signal.SIGINT, signal.SIG_IGN)
        print(Fore.RED + "\n\n🛑 Interrompido pelo usuário.")
        print(Fore.YELLOW + "🔒 [SEGURANÇA ATIVADA] Bloqueando novos comandos de interrupção.")
        print(Fore.CYAN + "⏳ Finalizando e empacotando os arquivos de forma segura. Por favor, aguarde...\n")
        
        exibir_resumo_custos()
        if NOME_BANCA_ATUAL:
            gerar_zip_exportacao(NOME_BANCA_ATUAL)
        sys.exit(0)
