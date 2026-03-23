import urllib.request
import json
import os
import time

base_url = 'https://api.enem.dev/v1'
out_dir = 'd:/Programming/EMPRESA - STACKUP SOFTWARE/Projetos/AprenderAI/tmp/enem-test-data'
os.makedirs(out_dir, exist_ok=True)

# 1. Get all available years
req = urllib.request.Request(base_url + '/exams', headers={'User-Agent': 'AuditScript/1.0'})
with urllib.request.urlopen(req, timeout=30) as resp:
    raw = resp.read().decode()

data = json.loads(raw)
print("API /exams response type:", type(data).__name__)

if isinstance(data, list):
    exams = data
elif isinstance(data, dict):
    exams = data.get('exams', data.get('data', []))
else:
    exams = []

years = []
for e in exams:
    if isinstance(e, dict):
        yr = e.get('year')
    elif isinstance(e, int):
        yr = e
    else:
        yr = None
    if yr:
        years.append(int(yr))

years = sorted(set(years))
print("Total distinct years:", len(years))

print("\n--- Fetching 20 questions sample per year (with delay) ---")
results = {}
for year in years:
    url = base_url + '/exams/' + str(year) + '/questions?limit=20&offset=0'
    req2 = urllib.request.Request(url, headers={'User-Agent': 'AuditScript/1.0'})
    try:
        # Adicionando delay de 3 segundos para evitar 429 Too Many Requests
        time.sleep(3)
        with urllib.request.urlopen(req2, timeout=30) as resp2:
            d = json.loads(resp2.read().decode())
        qs = d.get('questions', d.get('data', []))
        meta = d.get('metadata', {})

        empty_ctx = sum(1 for q in qs if not (q.get('context') or '').strip())
        has_files = sum(1 for q in qs if q.get('files'))
        intro_only = sum(1 for q in qs if not (q.get('context') or '').strip() and (q.get('alternativesIntroduction') or '').strip())
        alt_with_file = sum(1 for q in qs for a in q.get('alternatives', []) if a.get('file'))
        total_in_year = meta.get('total', len(qs))

        results[year] = {
            'total_in_api': total_in_year,
            'sampled': len(qs),
            'empty_context': empty_ctx,
            'intro_only': intro_only,
            'has_files': has_files,
            'alts_with_file': alt_with_file,
        }

        print(str(year) + ': total_api=' + str(total_in_year) + ', sampled=' + str(len(qs)) + ', empty_ctx=' + str(empty_ctx) + '/' + str(len(qs)) + ', intro_only=' + str(intro_only) + ', files_in_q=' + str(has_files) + ', alt_files=' + str(alt_with_file))

        fname = out_dir + '/year_' + str(year) + '_sample.json'
        with open(fname, 'w', encoding='utf-8') as f:
            json.dump({'year': year, 'total': total_in_year, 'sample': qs[:10]}, f, ensure_ascii=False, indent=2)
    except Exception as e2:
        print(str(year) + ': ERROR - ' + str(e2))

print("\n=== SUMMARY TABLE ===")
print("Year | TotalAPI | EmptyCtx/20 | IntroOnly | Files | AltFiles")
for yr, r in sorted(results.items()):
    print(str(yr) + ' | ' + str(r['total_in_api']) + ' | ' + str(r['empty_context']) + '/' + str(r['sampled']) + ' | ' + str(r['intro_only']) + ' | ' + str(r['has_files']) + ' | ' + str(r['alts_with_file']))

print("done")
