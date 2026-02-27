import os

# --- CONFIGURAÇÕES GERAIS ---
BASE_DIR = "Dados_Scraper"
BANCAS_FILE = "bancas.txt"
API_KEY_FILE = "apikey.txt"
API_PRICES_FILE = "api_prices.txt"
ESTADO_SCRAPING_FILE = os.path.join(BASE_DIR, "estado_scraping.json")

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
}

# --- VARIÁVEIS GLOBAIS DE ESTADO ---
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