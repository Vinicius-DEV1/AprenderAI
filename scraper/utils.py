import os
import json
import re
import zipfile
from datetime import datetime
import sys
from colorama import init, Fore, Style
import config

# Inicializa o colorama
init(autoreset=True)

def log(mensagem, cor=Fore.WHITE):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"{cor}[{timestamp}] {mensagem}")

def sanitize_name(name):
    return re.sub(r'[\\/*?:"<>|]', "", name).strip()

def inicializar_estrutura():
    if not os.path.exists(config.BASE_DIR):
        os.makedirs(config.BASE_DIR)
            
    if not os.path.exists(config.ESTADO_SCRAPING_FILE):
        with open(config.ESTADO_SCRAPING_FILE, "w", encoding="utf-8") as f:
            json.dump({}, f)
            
    if not os.path.exists(config.BANCAS_FILE):
        with open(config.BANCAS_FILE, "w", encoding="utf-8") as f:
            f.write('"FGV", "https://www.pciconcursos.com.br/provas/fgv"\n')
            f.write('"CESPE", "https://www.pciconcursos.com.br/provas/cespe"\n')
        log(f"⚠️ Arquivo '{config.BANCAS_FILE}' criado com exemplos. Edite-o se necessário.", Fore.YELLOW)
        
    if not os.path.exists(config.API_PRICES_FILE):
        with open(config.API_PRICES_FILE, "w", encoding="utf-8") as f:
            f.write("# Modelo, Preço Input (1M), Preço Output (1M)\n")
            f.write("models/gemini-1.5-flash,0.075,0.30\n")
            f.write("models/gemini-1.5-pro,1.25,5.00\n")
        log(f"⚠️ Arquivo '{config.API_PRICES_FILE}' criado com preços base. Atualize-o.", Fore.YELLOW)

# --- FUNÇÃO QUE FALTAVA ---
def carregar_precos_api():
    precos = {}
    if os.path.exists(config.API_PRICES_FILE):
        with open(config.API_PRICES_FILE, "r", encoding="utf-8") as f:
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
# --------------------------

def exibir_resumo_custos():
    print(Fore.CYAN + "\n" + "="*50)
    print(Fore.CYAN + "📊 RESUMO DE CONSUMO E CUSTOS DA SESSÃO")
    print(Fore.CYAN + "="*50)
    print(Fore.LIGHTBLUE_EX + f"🔹 Tokens de Input (Prompt): {config.TOTAL_PROMPT_TOKENS:,}")
    print(Fore.LIGHTBLUE_EX + f"🔹 Tokens de Output (Resposta): {config.TOTAL_COMPLETION_TOKENS:,}")
    print(Fore.GREEN + f"💵 CUSTO TOTAL ESTIMADO: U$ {config.TOTAL_COST_USD:.6f}")
    print(Fore.CYAN + "="*50 + "\n")

def carregar_bancas():
    inicializar_estrutura()
    bancas = []
    with open(config.BANCAS_FILE, "r", encoding="utf-8") as f:
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
        print(Fore.RED + f"❌ Nenhuma banca configurada no '{config.BANCAS_FILE}'.")
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

def obter_pagina_inicial(nome_banca):
    # Agora o estado fica isolado dentro da pasta da própria banca
    pasta_banca = os.path.join(config.BASE_DIR, nome_banca)
    arquivo_estado = os.path.join(pasta_banca, f"estado_{nome_banca.lower()}.json")
    
    if not os.path.exists(arquivo_estado):
        return 1
        
    with open(arquivo_estado, "r", encoding="utf-8") as f:
        estado = json.load(f)
    return estado.get("ultima_pagina", 1)

def salvar_progresso(nome_banca, pagina):
    pasta_banca = os.path.join(config.BASE_DIR, nome_banca)
    if not os.path.exists(pasta_banca):
        os.makedirs(pasta_banca)
        
    arquivo_estado = os.path.join(pasta_banca, f"estado_{nome_banca.lower()}.json")
    
    with open(arquivo_estado, "w", encoding="utf-8") as f:
        json.dump({"ultima_pagina": pagina}, f, indent=4)

def registrar_revisao_manual(banca, exam_id, cargo, numero_questao, motivo):
    arquivo_revisao = os.path.join(config.BASE_DIR, banca, f"revisao_{banca.lower()}.txt")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    linha = f"[{timestamp}] EXAM ID: {exam_id} | CARGO: {cargo} | QUESTÃO: {numero_questao} | MOTIVO: {motivo}\n"
    with open(arquivo_revisao, "a", encoding="utf-8") as f:
        f.write(linha)

def gerar_zip_exportacao(nome_banca):
    banca_dir = os.path.join(config.BASE_DIR, nome_banca)
    if not os.path.exists(banca_dir):
        return
        
    nome_zip = f"exportacao_{nome_banca.lower()}.zip"
    caminho_zip = os.path.join(config.BASE_DIR, nome_zip)
    
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