import urllib.request
import json
import os

base_url = 'https://api.enem.dev/v1'
out_dir = 'd:/Programming/EMPRESA - STACKUP SOFTWARE/Projetos/AprenderAI/tmp/enem-test-data'
os.makedirs(out_dir, exist_ok=True)

# Try different years to find questions with images
# ENEM natureza/matematica questions usually have images
years_to_try = [2022, 2021, 2020, 2019, 2018]

all_questions_with_images = []

for year in years_to_try:
    # Try offset 90 to get Ciencias da Natureza / Matematica
    for offset in [90, 0, 45]:
        url = base_url + '/exams/' + str(year) + '/questions?limit=20&offset=' + str(offset)
        req = urllib.request.Request(url, headers={'User-Agent': 'AuditScript/1.0'})
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                data = json.loads(resp.read().decode())
            questions = data.get('questions', data.get('data', []))
            for q in questions:
                has_files = bool(q.get('files'))
                has_alt_files = any(a.get('file') for a in q.get('alternatives', []))
                if has_files or has_alt_files:
                    all_questions_with_images.append((year, q))
                    if len(all_questions_with_images) >= 10:
                        break
            if len(all_questions_with_images) >= 10:
                break
        except Exception as e:
            print('Error fetching year', year, 'offset', offset, ':', e)
    if len(all_questions_with_images) >= 10:
        break

print('Questions with images found:', len(all_questions_with_images))

for i, (year, q) in enumerate(all_questions_with_images[:10]):
    fname = out_dir + '/img_question_' + str(i+1) + '_y' + str(year) + '.json'
    with open(fname, 'w', encoding='utf-8') as f:
        json.dump(q, f, ensure_ascii=False, indent=2)

    ctx_len = len(q.get('context', '') or '')
    files = q.get('files', [])
    alts = q.get('alternatives', [])
    alts_with_file = [(a.get('letter'), a.get('file')) for a in alts if a.get('file')]

    print('---')
    print('Year ' + str(year) + ' | index=' + str(q.get('index')) + ' | ctx_len=' + str(ctx_len))
    print('  question.files: ' + repr(files))
    print('  alts_with_file: ' + repr(alts_with_file))
    print('  context[:200]: ' + repr((q.get('context') or '')[:200]))
    print('  alternativesIntroduction[:200]: ' + repr((q.get('alternativesIntroduction') or '')[:200]))

print('Done')
