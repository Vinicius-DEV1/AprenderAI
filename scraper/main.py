import os
import shutil
import time
import json
import sys
import signal 
from datetime import datetime # <--- ADICIONE ESTA LINHA AQUI

# --- IMPORTAÇÃO DOS SEUS NOVOS MÓDULOS ---
import config
from utils import (
    log, sanitize_name, carregar_precos_api, exibir_resumo_custos, 
    selecionar_banca, obter_pagina_inicial, salvar_progresso, gerar_zip_exportacao
)
from database import setup_db, registrar_falha_no_banco
from ai_engine import selecionar_api_key, escolher_modelo_interativo, analise_visual_ia
from scraper_web import get_exam_links, download_pdfs

from colorama import init, Fore, Style

# Inicializa as cores do terminal
init(autoreset=True)

def main():
    print(Fore.CYAN + "\n" + "="*50)
    print(Fore.CYAN + "🛠️ MODO DE EXECUÇÃO")
    print(Fore.CYAN + "="*50)
    print(Fore.WHITE + "[1] Modo Normal (Produção)")
    print(Fore.WHITE + "[2] Modo Debug (Testar uma página específica)")
    
    modo_escolha = input(Fore.CYAN + "\n👉 Escolha o modo (1 ou 2): " + Style.RESET_ALL).strip()
    is_debug = (modo_escolha == '2')

    if is_debug:
        config.BASE_DIR = "Dados_Scraper_Debug"
        config.ESTADO_SCRAPING_FILE = os.path.join(config.BASE_DIR, "estado_scraping.json")
        if os.path.exists(config.BASE_DIR):
            shutil.rmtree(config.BASE_DIR)
        
        url_debug = input(Fore.CYAN + "\n👉 Insira o link da página (ex: https://www.pciconcursos.com.br/provas/vunesp/70): " + Style.RESET_ALL).strip()
        
        if "/download/" in url_debug:
            config.NOME_BANCA_ATUAL = "DEBUG_LINK_DIRETO"
        else:
            try:
                config.NOME_BANCA_ATUAL = url_debug.split('/provas/')[1].split('/')[0].upper()
            except IndexError:
                config.NOME_BANCA_ATUAL = "DEBUG_BANCA"
            
        url_banca = url_debug
        limite_provas = None
        pag_atual = 1
        log(f"Modo Debug ativado. Diretório '{config.BASE_DIR}' limpo. Testando a página: {url_debug}", Fore.YELLOW)
    else:
        banca_selecionada = selecionar_banca()
        config.NOME_BANCA_ATUAL = banca_selecionada["nome"]
        url_banca = banca_selecionada["url"]
        
        print(Fore.CYAN + "\n" + "="*50)
        entrada_limite = input(Fore.CYAN + "👉 Deseja extrair quantas provas nesta sessão? (Deixe em branco para extrair tudo): " + Style.RESET_ALL).strip()
        limite_provas = int(entrada_limite) if entrada_limite.isdigit() else None
        pag_atual = obter_pagina_inicial(config.NOME_BANCA_ATUAL)

    provas_processadas_sessao = 0
    atingiu_limite_global = False

    config.PRECOS_MODELOS = carregar_precos_api()
    config.CURRENT_API_KEY = selecionar_api_key()
    
    config.SELECTED_MODEL_NAME = escolher_modelo_interativo("ESCOLHA O MODELO EXTRATOR PRINCIPAL (Para ler questões da prova)")
    config.ORGANIZER_MODEL_NAME = escolher_modelo_interativo("ESCOLHA O MODELO CLASSIFICADOR/ORGANIZADOR (Para parear múltiplos PDFs)", sugerir_flash=True)

    conn = setup_db(config.NOME_BANCA_ATUAL)
    cursor = conn.cursor()
    
    log(f"=== INICIANDO SCRAPER | BANCA: {config.NOME_BANCA_ATUAL} | PÁGINA INICIAL: {pag_atual} ===", Fore.MAGENTA)
    
    while True:
        if atingiu_limite_global:
            break
            
        if is_debug and "/download/" in url_banca:
            links = [url_banca]
        else:
            links = get_exam_links(url_banca, pag_atual)
            
        if not links:
            log("Fim da raspagem desta banca (Sem mais páginas).", Fore.CYAN)
            break
            
        for i, link in enumerate(links, 1):
            if atingiu_limite_global:
                break
                
            log(f"\n--- {config.NOME_BANCA_ATUAL} | PÁGINA {pag_atual} | LINK {i}/{len(links)} ---", Fore.MAGENTA)
            
            meta, pasta_prova, grupos_validos = download_pdfs(link, config.NOME_BANCA_ATUAL)
            
            if not grupos_validos:
                conn = setup_db(config.NOME_BANCA_ATUAL)
                registrar_falha_no_banco(conn, link, "Nenhum grupo válido encontrado (Ignorado pelo Bypass ou Falha no Organizador).")
                log(f"[AVISO] Não foi possível agrupar cadernos/gabaritos válidos nesta URL.", Fore.YELLOW)
                time.sleep(2)
                continue
                
            for grupo in grupos_validos:
                if limite_provas and provas_processadas_sessao >= limite_provas:
                    log(f"🎯 Limite escolhido pelo utilizador ({limite_provas} provas) foi atingido.", Fore.GREEN)
                    atingiu_limite_global = True
                    break
                    
                provas_paths = grupo['provas']
                g_path = grupo['gabarito']
                identificador = sanitize_name(grupo['identificador'])
                tipo_prova = grupo.get('tipo_prova', 'Mista') 
                
                cargo_final = meta['cargo']
                link_virtual = link
                if identificador and identificador.lower() != 'geral':
                    cargo_final = f"{meta['cargo']} - {identificador.upper()}"
                    link_virtual = f"{link}#{identificador.replace(' ', '')}"
                    
                # --- NOVA VERIFICAÇÃO BLINDADA ANTI-DUPLICATA ---
                ja_processado = False
                for prova_path in provas_paths:
                    nome_pdf = os.path.basename(prova_path)
                    cursor.execute("""
                        SELECT q.id FROM questions q
                        JOIN exams e ON q.exam_id = e.id
                        WHERE e.source_url LIKE ? AND q.arquivo_origem = ?
                    """, (f"{link}%", nome_pdf))
                    
                    if cursor.fetchone():
                        ja_processado = True
                        break

                if ja_processado:
                    log(f"Os PDFs deste grupo já foram processados anteriormente neste link. Saltando...", Fore.YELLOW)
                    continue
                # ------------------------------------------------
                
                try:
                    data_extracao_atual = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    
                    cursor.execute('''INSERT INTO exams (organization, role, year, institution, origin, source_url, extracted_at)
                                      VALUES (?,?,?,?,?,?,?)''',
                                   (config.NOME_BANCA_ATUAL, cargo_final, meta['ano'], meta['orgao'], meta['instituicao'], link_virtual, data_extracao_atual))
                    exam_id = cursor.lastrowid
                    
                    questoes_salvas_grupo = 0
                    for prova_path in provas_paths:
                        nome_arquivo_origem = os.path.basename(prova_path)
                        
                        # CHAMA A IA
                        dados_questoes = analise_visual_ia(prova_path, g_path, pasta_prova, exam_id, cargo_final, config.NOME_BANCA_ATUAL, tipo_prova)
                        
                        if dados_questoes:
                            total_encontradas = len(dados_questoes)
                            
                            for q in dados_questoes:
                                alts_json = json.dumps(q.get('alternativas'), ensure_ascii=False) if q.get('alternativas') else "{}"
                                status = 'review' if (q.get('imagens_arquivos') or q.get('alternativas_visuais')) else 'approved'
                                
                                resp_disc_bruta = q.get('resposta_discursiva')
                                if isinstance(resp_disc_bruta, dict):
                                    resp_disc_final = json.dumps(resp_disc_bruta, ensure_ascii=False)
                                else:
                                    resp_disc_final = resp_disc_bruta
                                
                                cursor.execute('''INSERT INTO questions
                                    (exam_id, number, tipo_questao, arquivo_origem, statement, alternatives, correct_answer, discursive_answer, pdf_page, image_path, review_status)
                                    VALUES (?,?,?,?,?,?,?,?,?,?,?)''',
                                    (exam_id, 
                                     str(q.get('numero')), 
                                     q.get('tipo_questao', tipo_prova), 
                                     nome_arquivo_origem,
                                     q.get('enunciado'), 
                                     alts_json, 
                                     q.get('gabarito'), 
                                     resp_disc_final, 
                                     q.get('pagina'), 
                                     q.get('imagens_arquivos'), 
                                     status))
                                
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
                                                   
                                questoes_salvas_grupo += 1
                                
                            log(f"   ↳ {nome_arquivo_origem}: Encontradas {total_encontradas} questões.", Fore.LIGHTBLACK_EX)
                            
                        else:
                            registrar_falha_no_banco(conn, link_virtual, f"IA falhou ao ler o caderno {nome_arquivo_origem}")
                            log(f"[ERRO] IA principal não retornou dados para {nome_arquivo_origem}.", Fore.RED)

                    conn.commit()
                    cursor.execute("DELETE FROM failed_scrapes WHERE url = ?", (link_virtual,))
                    conn.commit()
                    log(f"[OK] Exam ID {exam_id} salvo com {questoes_salvas_grupo} questões consolidadas!", Fore.GREEN)
                
                except Exception as e:
                    conn.rollback()
                    registrar_falha_no_banco(conn, link_virtual, f"Erro crítico de banco: {str(e)}")
                    log(f"[ERRO CRÍTICO] Falha ao gravar no banco: {e}", Fore.LIGHTRED_EX)
                    
                provas_processadas_sessao += 1
                time.sleep(5)
            
        if is_debug:
            log("🎯 Modo Debug finalizado (Apenas a página solicitada foi processada).", Fore.YELLOW)
            break
            
        if not atingiu_limite_global:
            salvar_progresso(config.NOME_BANCA_ATUAL, pag_atual)
            pag_atual += 1

    exibir_resumo_custos()
    gerar_zip_exportacao(config.NOME_BANCA_ATUAL)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        signal.signal(signal.SIGINT, signal.SIG_IGN)
        print(Fore.RED + "\n\n🛑 Interrompido pelo utilizador.")
        print(Fore.YELLOW + "🔒 [SEGURANÇA ATIVADA] A bloquear novos comandos de interrupção.")
        print(Fore.CYAN + "⏳ A finalizar e empacotar os ficheiros de forma segura. Por favor, aguarde...\n")
        
        exibir_resumo_custos()
        if config.NOME_BANCA_ATUAL:
            gerar_zip_exportacao(config.NOME_BANCA_ATUAL)
        sys.exit(0)