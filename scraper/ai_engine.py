import os
import time
import json
import sys
import re
import fitz  # PyMuPDF
from PIL import Image
import google.generativeai as genai
from colorama import Fore, Style
import config
from utils import log, registrar_revisao_manual

def carregar_chaves_do_arquivo():
    if not os.path.exists(config.API_KEY_FILE):
        print(Fore.RED + f"\n❌ Ficheiro '{config.API_KEY_FILE}' não encontrado!")
        sys.exit(1)
    
    chaves_encontradas = []
    with open(config.API_KEY_FILE, "r", encoding="utf-8") as f:
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
    print(Fore.YELLOW + "\n" + "="*50)
    print(Fore.YELLOW + "🔑 SELEÇÃO DE CONTA INICIAL (API KEY)")
    print(Fore.YELLOW + "="*50)
    
    config.CHAVES_DISPONIVEIS = carregar_chaves_do_arquivo()
    if len(config.CHAVES_DISPONIVEIS) == 1:
        print(Fore.CYAN + f"\nℹ️ Apenas uma chave encontrada: {config.CHAVES_DISPONIVEIS[0]['apelido']}")
        config.INDICE_CHAVE_ATUAL = 0
        config.CURRENT_API_KEY = config.CHAVES_DISPONIVEIS[0]['key']
        genai.configure(api_key=config.CURRENT_API_KEY)
        return config.CURRENT_API_KEY

    for i, item in enumerate(config.CHAVES_DISPONIVEIS, 1):
        chave_mascarada = f"...{item['key'][-6:]}"
        print(Fore.WHITE + f"[{i}] {item['apelido']} \t({chave_mascarada})")
    
    while True:
        try:
            escolha = input(Fore.CYAN + "\n👉 Digite o NÚMERO da chave para iniciar: " + Style.RESET_ALL)
            idx = int(escolha) - 1
            if 0 <= idx < len(config.CHAVES_DISPONIVEIS):
                config.INDICE_CHAVE_ATUAL = idx
                escolhida = config.CHAVES_DISPONIVEIS[idx]
                print(Fore.GREEN + f"\n✅ CONTA INICIAL SELECIONADA: {escolhida['apelido']}")
                config.CURRENT_API_KEY = escolhida['key']
                genai.configure(api_key=config.CURRENT_API_KEY)
                return config.CURRENT_API_KEY
        except ValueError:
            pass
        print(Fore.RED + "❌ Inválido.")

def rotacionar_api_key():
    if len(config.CHAVES_DISPONIVEIS) <= 1:
        log("⚠️ Limite atingido, mas só há UMA chave configurada. Aguardando 65s para reset...", Fore.YELLOW)
        time.sleep(65)
        return

    config.INDICE_CHAVE_ATUAL = (config.INDICE_CHAVE_ATUAL + 1) % len(config.CHAVES_DISPONIVEIS)
    nova_chave = config.CHAVES_DISPONIVEIS[config.INDICE_CHAVE_ATUAL]
    
    config.CURRENT_API_KEY = nova_chave['key']
    genai.configure(api_key=config.CURRENT_API_KEY)
    
    log(f"🔄 COTA EXCEDIDA (429)! Rotacionando automaticamente para a chave: [{nova_chave['apelido']}]...", Fore.YELLOW)
    
    if config.INDICE_CHAVE_ATUAL == 0:
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

def organizar_arquivos_com_ia(lista_arquivos, cargo, banca):
    genai.configure(api_key=config.CURRENT_API_KEY)
    model = genai.GenerativeModel(config.ORGANIZER_MODEL_NAME)
    
    prompt_organizador = f"""
    Você é um classificador especializado em ficheiros de concursos públicos.
    Cargo alvo: {cargo}
    Banca: {banca}
    Ficheiros encontrados: {lista_arquivos}

    Sua tarefa:
    Identifique e separe os cadernos de prova Objetiva e Discursiva, associando-os aos seus respectivos gabaritos/padrões de resposta.

    🚨 REGRAS DE SEPARAÇÃO (MUITO IMPORTANTE):
    1. Se houver Prova Objetiva e Prova Discursiva/Redação em PDFs separados, crie DOIS grupos distintos.
    2. Se ambas as provas usarem o MESMO ficheiro de gabarito, você DEVE colocar o mesmo ficheiro de gabarito nos dois grupos.
    3. Se as questões Objetivas e Discursivas/Redação estiverem misturadas DENTRO DO MESMO PDF (Ex: prova_completa.pdf), crie dois grupos separados que apontam para o MESMO 'caderno_de_prova', mas defina o 'tipo_prova' de um como 'Objetiva' e do outro como 'Discursiva'.
    4. Se houver um "Padrão de Resposta" ou "Espelho" para a Discursiva/Redação, coloque-o na chave 'gabarito_correspondente' do grupo Discursiva.
    5. Se a discursiva não tiver gabarito, retorne null em 'gabarito_correspondente'.

    Retorne APENAS um JSON válido, neste formato estrito:
    [
      {{
        "identificador": "Nome descritivo curto (Ex: 2 Tenente - Objetiva)",
        "tipo_prova": "ESCREVA_AQUI", // DEVE SER ESTRITAMENTE UMA DESTAS 3 OPÇÕES: "Objetiva", "Discursiva" ou "Mista"
        "cadernos_de_prova": ["arquivo_prova1.pdf"], // Lista de strings
        "gabarito_correspondente": "arquivo_gabarito.pdf" // String ou null
      }}
    ]
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
            modelo_limpo = config.ORGANIZER_MODEL_NAME.replace("models/", "")
            modelo_busca = f"{modelo_limpo}<=200k" if f"{modelo_limpo}<=200k" in config.PRECOS_MODELOS else modelo_limpo
                
            if modelo_busca in config.PRECOS_MODELOS:
                preco_in = config.PRECOS_MODELOS[modelo_busca]['input']
                preco_out = config.PRECOS_MODELOS[modelo_busca]['output']
                custo_org = (p_tokens / 1_000_000) * preco_in + (c_tokens / 1_000_000) * preco_out
            
            config.TOTAL_PROMPT_TOKENS += p_tokens
            config.TOTAL_COMPLETION_TOKENS += c_tokens
            config.TOTAL_COST_USD += custo_org
            
            # --- BYPASS: Bug do SDK do Google ---
            try:
                texto_json = res.text.strip()
            except Exception:
                texto_json = "".join([p.text for p in res.candidates[0].content.parts]).strip()
            # ------------------------------------
            if "```json" in texto_json: texto_json = texto_json.split("```json")[1].split("```")[0].strip()
            elif "```" in texto_json: texto_json = texto_json.split("```")[1].split("```")[0].strip()
            
            # --- VACINA ANTI-CRASH DE JSON ---
            # Escapa as barras invertidas perdidas (\u) que causam o erro fatal no Python
            text = re.sub(r'\\u(?![0-9a-fA-F]{4})', r'\\\\u', text)
            # ---------------------------------

            grupos = json.loads(texto_json)
            return grupos
            
        except Exception as e:
            error_msg = str(e).lower()
            if "429" in error_msg or "quota" in error_msg:
                rotacionar_api_key()
                model = genai.GenerativeModel(config.ORGANIZER_MODEL_NAME)
                continue
            else:
                log(f"   [Filtro IA] Erro ao tentar classificar os ficheiros com IA: {e}", Fore.RED)
                return []

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

def analise_visual_ia(prova_path, gabarito_path, pasta_prova, exam_id, nome_cargo, banca, tipo_prova):
    nome_arquivo = os.path.basename(prova_path)
    log(f"IA: Enviando Caderno '{nome_arquivo}' (Modo: {tipo_prova}) para {config.SELECTED_MODEL_NAME}...", Fore.CYAN)
    genai.configure(api_key=config.CURRENT_API_KEY)
    model = genai.GenerativeModel(config.SELECTED_MODEL_NAME)
    
    try:
        f_p = genai.upload_file(path=prova_path)
        f_g = None
        if gabarito_path and os.path.exists(gabarito_path):
            f_g = genai.upload_file(path=gabarito_path)
        
        todos_ativos = False
        while not todos_ativos:
            todos_ativos = True
            f_p = genai.get_file(f_p.name)
            if f_p.state.name == "PROCESSING":
                todos_ativos = False
            if f_g:
                f_g = genai.get_file(f_g.name)
                if f_g.state.name == "PROCESSING":
                    todos_ativos = False
            if not todos_ativos:
                time.sleep(2)
                
    except Exception as e:
        log(f"Erro no upload para Gemini: {e}", Fore.RED)
        return []

    diretiva_foco = ""
    if tipo_prova.lower() == "objetiva":
        diretiva_foco = "🎯 FOCO ABSOLUTO: Extraia APENAS as questões OBJETIVAS (múltipla escolha ou certo/errado). IGNORE completamente qualquer questão discursiva, redação ou estudo de caso."
    elif tipo_prova.lower() == "discursiva":
        diretiva_foco = "🎯 FOCO ABSOLUTO: Extraia as questões DISCURSIVAS (abertas e estudos de caso) E TAMBÉM as propostas de REDAÇÃO. IGNORE completamente as questões objetivas."
    else:
        diretiva_foco = "🎯 FOCO: Extraia TODAS as questões (Objetivas, Discursivas e Redação) presentes no documento."

    prompt = f"""
    Extraia as questões das PROVAS em JSON. Considere todos os cadernos de prova fornecidos em conjunto com o gabarito.
    
    {diretiva_foco}
    
    🚨 REGRA DE OURO (ANTI-PREGUIÇA): É ESTRITAMENTE PROIBIDO parar pela metade, resumir ou omitir questões. Você DEVE extrair ABSOLUTAMENTE TODAS AS QUESTÕES DA SUA ÁREA DE FOCO, da número 1 até a ÚLTIMA (mesmo que a prova tenha 80, 100 ou mais questões). Leia o documento até a última página e não abrevie o JSON!
    
    🚨 ESTRITAMENTE PROIBIDO usar seu próprio conhecimento para tentar "resolver" ou responder às questões. Você é um Transcritor e Extrator de Dados.

    ESTRUTURA DE DADOS ESPERADA PARA CADA QUESTÃO:
    1. 'numero': O número da questão (ex: "1"). Se for uma Proposta de Redação sem número, preencha com "Redação".
    2. 'tipo_questao': Classifique como "Objetiva", "Discursiva" ou "Redação".
    3. 'enunciado': Texto da pergunta.
       - 🚨 REGRA PARA DISCURSIVAS COM ITENS: Se a questão discursiva tiver um texto base (cenário/problema) e depois perguntas divididas em itens (a, b, c...), coloque AQUI APENAS O TEXTO BASE (o contexto/cenário).
       - 🚨 REGRA PARA REDAÇÃO: Se for Redação, transcreva na íntegra os "Textos Motivadores" (Texto 1, Texto 2) seguidos pela proposta de tema no 'enunciado'.
    4. 'alternativas': 
       - Se "Objetiva": Um objeto JSON com as opções. Ex: {{"A": "...", "B": "..."}}. (Para Certo/Errado do Cebraspe use {{"C": "Certo", "E": "Errado"}}).
       - Se "Discursiva" COM ITENS (a, b, c): Um objeto JSON mapeando as subperguntas. Ex: {{"a": "Qual o diagnóstico?", "b": "Qual o tratamento?"}}.
       - Se "Discursiva" SEM ITENS ou "Redação": Retorne um objeto vazio {{}}.
    5. 'gabarito': 
       - Se "Objetiva": A letra correta (ex: "A") baseada no PDF de gabarito. Se anulada, escreva "Anulada". NUNCA tente adivinhar.
       - Se "Discursiva" ou "Redação": retorne null.
    6. 'resposta_discursiva': 
       - Se for "Discursiva" COM ITENS e houver Espelho de Correção: Retorne um objeto JSON com a resposta esperada de cada item. Ex: {{"a": "Resposta da a...", "b": "Resposta da b..."}}.
       - Se for "Discursiva" SEM itens ou "Redação" e houver Espelho: Transcreva o texto completo.
       - Se não houver espelho ou padrão de resposta: retorne null.
    7. 'disciplinas': Lista de strings (ex: ["Português", "Redação"]).
    8. 'assuntos': Lista de strings (ex: ["Dissertação Argumentativa"]).
    
    ATENÇÃO EXTREMA A IMAGENS, GRÁFICOS, TABELAS E FÓRMULAS:
    1. GATILHOS OBRIGATÓRIOS: Se o texto mencionar "gráfico", "figura a seguir", "tabela", "charge", "esquema", "imagem", ou "ilustra", VOCÊ É OBRIGADO a definir "contem_imagem": true e capturar a área dessa figura.
    2. REGRA DO BLOCO DE ALTERNATIVAS: Se as alternativas (A, B, C, D) contiverem FÓRMULAS FÍSICAS/MATEMÁTICAS complexas ou gráficos, desenhe UMA ÚNICA CAIXA gigante englobando todo o bloco de alternativas juntas.
    3. MÚLTIPLAS IMAGENS: Se a questão tiver um desenho no enunciado E fórmulas complexas nas alternativas, você deve retornar DUAS caixas na lista "boxes_imagens".
    4. MARGEM DE SEGURANÇA: Expanda os limites das caixas [ymin, xmin, ymax, xmax] em 5% para fora.
    
    Se houver elemento visual, retorne uma LISTA DE COORDENADAS na chave "boxes_imagens". Ex: [[ymin, xmin, ymax, xmax]] (escala 0 a 1000).
    Se NÃO houver imagens, defina "contem_imagem": false e retorne "boxes_imagens": [].

    ATENÇÃO À SINTAXE JSON: É estritamente proibido deixar vírgulas sobrando no final de objetos ou listas.

    RETORNE APENAS UM JSON VÁLIDO no seguinte formato, sem formatação markdown em volta:
    [
      {{ 
        "numero": "1", 
        "tipo_questao": "Discursiva",
        "enunciado": "Um cão da raça pastor alemão deu entrada na clínica com hipertermia...", 
        "alternativas": {{"a": "Analise o quadro clínico.", "b": "Proponha o tratamento."}}, 
        "gabarito": null, 
        "resposta_discursiva": {{"a": "Espera-se que o aluno responda Leptospirose...", "b": "O tratamento é..."}},
        "disciplinas": ["Clínica Veterinária"], 
        "assuntos": ["Doenças Infecciosas"],
        "pagina": 1, 
        "contem_imagem": false,
        "boxes_imagens": []
      }}
    ]
    """
    
    while True:
        try:
            conteudo_requisicao = [prompt, f_p]
            if f_g:
                conteudo_requisicao.append(f_g)

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
            modelo_limpo = config.SELECTED_MODEL_NAME.replace("models/", "")
            total_tokens = p_tokens + c_tokens
            modelo_busca = f"{modelo_limpo}>200k" if total_tokens > 200000 and f"{modelo_limpo}>200k" in config.PRECOS_MODELOS else f"{modelo_limpo}<=200k" if f"{modelo_limpo}<=200k" in config.PRECOS_MODELOS else modelo_limpo

            if modelo_busca in config.PRECOS_MODELOS:
                preco_in = config.PRECOS_MODELOS[modelo_busca]['input']
                preco_out = config.PRECOS_MODELOS[modelo_busca]['output']
                custo_prova = (p_tokens / 1_000_000) * preco_in + (c_tokens / 1_000_000) * preco_out
            
            config.TOTAL_PROMPT_TOKENS += p_tokens
            config.TOTAL_COMPLETION_TOKENS += c_tokens
            config.TOTAL_COST_USD += custo_prova
            
            log(f"🪙  Tokens usados (Extrator): {p_tokens} in | {c_tokens} out", Fore.BLUE)
            log(f"💵 Custo desta extração: U$ {custo_prova:.6f}", Fore.BLUE)

            # --- BYPASS: Bug do SDK do Google ---
            try:
                text = res.text.strip()
            except Exception:
                text = "".join([p.text for p in res.candidates[0].content.parts]).strip()
            # ------------------------------------
            if "```json" in text: text = text.split("```json")[1].split("```")[0].strip()
            elif "```" in text: text = text.split("```")[1].split("```")[0].strip()
            
            questoes = json.loads(text)
            
            pasta_imgs = os.path.join(pasta_prova, "imagens_questoes")
            if not os.path.exists(pasta_imgs): os.makedirs(pasta_imgs)
            
            for q in questoes:
                q['imagens_arquivos'] = ""
                arquivos_recortados = [] 
                
                if q.get('contem_imagem') and q.get('boxes_imagens'):
                    pag = int(q.get('pagina', 1))
                    boxes = q.get('boxes_imagens', [])
                    
                    if len(boxes) == 4 and isinstance(boxes[0], (int, float)):
                        boxes = [boxes]
                    
                    for idx, box in enumerate(boxes):
                        if isinstance(box, list) and len(box) == 4 and box != [0,0,0,0]:
                            safe_num = str(q['numero']).replace(" ", "_")
                            nome_arq = f"e{exam_id}_q{safe_num}_img_{idx+1}.jpg"
                            caminho_final = os.path.join(pasta_imgs, nome_arq)
                            if recortar_area_da_pagina(prova_path, pag, box, caminho_final):
                                arquivos_recortados.append(nome_arq)
                
                if arquivos_recortados:
                    q['imagens_arquivos'] = ",".join(arquivos_recortados)
                    registrar_revisao_manual(banca, exam_id, nome_cargo, str(q['numero']), "Questão contém Imagens/Figuras")
                        
            return questoes
            
        except Exception as e:
            error_msg = str(e).lower()
            if "429" in error_msg or "quota" in error_msg:
                rotacionar_api_key()
                model = genai.GenerativeModel(config.SELECTED_MODEL_NAME)
                continue 
            else:
                log(f"❌ Erro fatal na extração JSON da IA: {e}", Fore.RED)
                return []