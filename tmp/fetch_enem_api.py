import urllib.request
import json
import os

base_url = 'https://api.enem.dev/v1'
out_dir = 'd:/Programming/EMPRESA - STACKUP SOFTWARE/Projetos/AprenderAI/tmp/enem-test-data'
os.makedirs(out_dir, exist_ok=True)

url = base_url + '/exams/2023/questions?limit=10&offset=0'
req = urllib.request.Request(url, headers={'User-Agent': 'AuditScript/1.0'})
with urllib.request.urlopen(req, timeout=30) as resp:
    data = json.loads(resp.read().decode())

questions = data.get('questions', data.get('data', []))
meta = data.get('metadata', {})
print('Total questions in response:', len(questions))
print('Metadata:', json.dumps(meta))

for i, q in enumerate(questions[:10]):
    fname = out_dir + '/question_' + str(i+1) + '.json'
    with open(fname, 'w', encoding='utf-8') as f:
        json.dump(q, f, ensure_ascii=False, indent=2)
    ctx_len = len(q.get('context', '') or '')
    title_len = len(q.get('title', '') or '')
    files_count = len(q.get('files', []))
    alt_count = len(q.get('alternatives', []))
    alts_with_file = sum(1 for a in q.get('alternatives', []) if a.get('file'))
    intro_len = len(q.get('alternativesIntroduction', '') or '')
    print('Q' + str(i+1) + ': index=' + str(q.get('index')) + ', ctx_len=' + str(ctx_len) + ', title_len=' + str(title_len) + ', intro_len=' + str(intro_len) + ', files=' + str(files_count) + ', alts=' + str(alt_count) + ', alts_with_file=' + str(alts_with_file))
    ctx = q.get('context') or ''
    print('  context_preview: ' + repr(ctx[:150]))
    print('  files: ' + repr(q.get('files', [])))
    if q.get('alternatives'):
        for alt in q['alternatives'][:5]:
            print('    alt ' + str(alt.get('letter')) + ': text_len=' + str(len(alt.get('text', '') or '')) + ', file=' + str(alt.get('file')))

print('Done. Files saved to:', out_dir)
