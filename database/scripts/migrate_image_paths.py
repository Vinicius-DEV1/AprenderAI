import os
import sqlite3
import re
import hashlib

# Configurações de caminhos
BASE_DIR = r"d:\Programming\EMPRESA - STACKUP SOFTWARE\Projetos\AprovadoAI"
DB_PATH = os.path.join(BASE_DIR, "database", "database.sqlite")
PUBLIC_STORAGE = os.path.join(BASE_DIR, "storage", "app", "public")

def migrate():
    if not os.path.exists(DB_PATH):
        print(f"Erro: Banco de dados não encontrado em {DB_PATH}")
        return

    # 1. Mapear nova estrutura: questions/images/{year}/* -> (year, size)
    print("Mapeando nova estrutura de imagens...")
    new_images_map = {} # (year, size) -> relative_path
    
    questions_img_dir = os.path.join(PUBLIC_STORAGE, "questions", "images")
    if os.path.exists(questions_img_dir):
        for year_dir in os.listdir(questions_img_dir):
            year_path = os.path.join(questions_img_dir, year_dir)
            if os.path.isdir(year_path):
                try:
                    year = int(year_dir)
                    for file in os.listdir(year_path):
                        file_path = os.path.join(year_path, file)
                        if os.path.isfile(file_path):
                            size = os.path.getsize(file_path)
                            # Se houver duplicatas de tamanho, o último ganha (geralmente ok)
                            new_images_map[(year, size)] = f"questions/images/{year}/{file}"
                except ValueError:
                    continue

    print(f"Mapeados {len(new_images_map)} arquivos na nova estrutura.")

    # 2. Conectar ao banco e buscar questões com caminhos antigos
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    cursor.execute("SELECT id, statement FROM questions WHERE statement LIKE '%/storage/enem/%'")
    rows = cursor.fetchall()
    print(f"Encontradas {len(rows)} questões para análise.")

    migrated_count = 0
    not_found_count = 0
    
    # Regex: ![](/storage/enem/2020/context/uuid.png)
    pattern = re.compile(r'!\[(.*?)\]\(/storage/enem/(\d{4})/context/(.*?)\)')

    for q_id, statement in rows:
        if not statement: continue
        
        has_changes = False
        
        def replace_path(match):
            nonlocal has_changes, migrated_count, not_found_count
            alt = match.group(1)
            year = int(match.group(2))
            filename = match.group(3)
            
            old_rel_path = f"enem/{year}/context/{filename}"
            old_abs_path = os.path.join(PUBLIC_STORAGE, old_rel_path)
            
            if os.path.exists(old_abs_path):
                size = os.path.getsize(old_abs_path)
                if (year, size) in new_images_map:
                    new_rel_path = new_images_map[(year, size)]
                    has_changes = True
                    migrated_count += 1
                    return f"![{alt}](/storage/{new_rel_path})"
            
            not_found_count += 1
            return match.group(0)

        new_statement = pattern.sub(replace_path, statement)
        
        if has_changes:
            cursor.execute("UPDATE questions SET statement = ? WHERE id = ?", (new_statement, q_id))

    conn.commit()
    conn.close()
    
    print("\n--- Resultados da Migração ---")
    print(f"Questões alteradas: {migrated_count}")
    print(f"Arquivos não encontrados/mapeados: {not_found_count}")
    print("Processo concluído.")

if __name__ == "__main__":
    migrate()
