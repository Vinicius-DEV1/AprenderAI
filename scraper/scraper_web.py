import os
import requests
from bs4 import BeautifulSoup
from colorama import Fore
import config
from utils import log, sanitize_name
from ai_engine import organizar_arquivos_com_ia

def get_exam_links(url_banca, pagina):
    url = url_banca if pagina == 1 else f"{url_banca}/{pagina}"
    try:
        res = requests.get(url, headers=config.HEADERS)
        if res.status_code != 200: return None
        soup = BeautifulSoup(res.text, 'html.parser')
        table = soup.find('table', {'id': 'lista_provas'})
        if not table: return None
        
        links = [a['href'] for a in table.find_all('a', class_='prova_download')]
        return list(dict.fromkeys(links))
    except Exception as e:
        log(f"Erro ao procurar links: {e}", Fore.RED)
        return None

def download_pdfs(url, banca_nome):
    try:
        res = requests.get(url, headers=config.HEADERS)
        soup = BeautifulSoup(res.text, 'html.parser')
        meta = {'cargo': 'N/A', 'ano': 'N/A', 'orgao': 'N/A', 'instituicao': 'N/A'}
        for li in soup.find_all('li', class_='mb-2'):
            t = li.get_text()
            if "Cargo:" in t: meta['cargo'] = li.find('a').text.strip() if li.find('a') else t.split("Cargo:")[-1].strip()
            elif "Ano:" in t: meta['ano'] = li.find('span').text.strip() if li.find('span') else t.split("Ano:")[-1].strip()
            elif "Órgão:" in t: meta['orgao'] = li.find('a').text.strip() if li.find('a') else t.split("Órgão:")[-1].strip()
            elif "Instituição:" in t: meta['instituicao'] = li.find('a').text.strip() if li.find('a') else t.split("Instituição:")[-1].strip()
        
        nome_pasta = f"{sanitize_name(meta['cargo'])}_{meta['ano']}"
        pasta_prova = os.path.join(config.BASE_DIR, banca_nome, "Provas", nome_pasta)
        
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
                            with open(local, 'wb') as f: f.write(requests.get(f_url, headers=config.HEADERS).content)
                        arquivos_baixados.append(nome)

        arquivos_validos = []
        for nome in arquivos_baixados:
            nome_min = nome.lower()
            if any(x in nome_min for x in ['pratica', 'titulos', 'edital']): 
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
                log("   [Atalho] Par óbvio detetado (1 Prova + 1 Gabarito). Ignorando IA Organizadora.", Fore.GREEN)
                pares_validos = [{
                    'identificador': 'Geral',
                    'tipo_prova': 'Mista', 
                    'provas': [os.path.join(pasta_prova, prova_file)],
                    'gabarito': os.path.join(pasta_prova, gabarito_file)
                }]
                return meta, pasta_prova, pares_validos

        grupos_organizados = []
        if arquivos_validos:
            log(f"   [Organizador] Cenário complexo. Chamando classificador {config.ORGANIZER_MODEL_NAME}...", Fore.YELLOW)
            grupos_organizados = organizar_arquivos_com_ia(arquivos_validos, meta['cargo'], banca_nome)
        
        pares_validos = []
        for grupo in grupos_organizados:
            gabarito = grupo.get('gabarito_correspondente')
            provas = grupo.get('cadernos_de_prova', [])
            identificador = grupo.get('identificador', 'Geral')
            tipo_prova = grupo.get('tipo_prova', 'Mista')
            
            if all(p in arquivos_validos for p in provas):
                gabarito_path = os.path.join(pasta_prova, gabarito) if gabarito and gabarito in arquivos_validos else None
                pares_validos.append({
                    'identificador': identificador,
                    'tipo_prova': tipo_prova,
                    'provas': [os.path.join(pasta_prova, p) for p in provas],
                    'gabarito': gabarito_path
                })
            else:
                log(f"   [Aviso] Grupo '{identificador}' formado pela IA contém ficheiros em falta/descartados.", Fore.YELLOW)

        return meta, pasta_prova, pares_validos
        
    except Exception as e:
        log(f"Erro no download ou organização: {e}", Fore.RED)
        return {}, "", []