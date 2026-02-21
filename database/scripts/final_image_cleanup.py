import os
import sqlite3
import re
import shutil

# Configurações de caminhos
BASE_DIR = r"d:\Programming\EMPRESA - STACKUP SOFTWARE\Projetos\AprovadoAI"
DB_PATH = os.path.join(BASE_DIR, "database", "database.sqlite")
PUBLIC_STORAGE = os.path.join(BASE_DIR, "storage", "app", "public")

def final_cleanup():
    if not os.path.exists(DB_PATH):
        print(f"Erro: Banco de dados não encontrado em {DB_PATH}")
        return

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    old_base_dir = os.path.join(PUBLIC_STORAGE, "enem")
    if not os.path.exists(old_base_dir):
        print("Pasta 'enem' não encontrada. Nada para limpar.")
        return

    print("--- Iniciando Cleanup Final do Sistema Antigo de Imagens ---")

    migrated_files = 0
    updated_questions = 0
    updated_alternatives = 0

    # 1. Percorrer a pasta enem/ e mover arquivos para questions/images/
    for root, dirs, files in os.walk(old_base_dir):
        for file in files:
            # Identificar ano e tipo pelo path
            # path esperado: .../enem/{year}/{context|alternatives}/{filename}
            parts = os.path.relpath(root, PUBLIC_STORAGE).split(os.sep)
            if len(parts) < 2: continue
            
            year = parts[1]
            old_rel_path = os.path.join(os.path.relpath(root, PUBLIC_STORAGE), file).replace('\\', '/')
            
            # Novo destino padrão
            new_dir = os.path.join(PUBLIC_STORAGE, "questions", "images", year)
            if not os.path.exists(new_dir):
                os.makedirs(new_dir)
            
            new_filename = f"legacy_{file}" # Prefixar para evitar colisões
            new_rel_path = f"questions/images/{year}/{new_filename}"
            new_abs_path = os.path.join(PUBLIC_STORAGE, new_rel_path.replace('/', os.sep))

            # Mover arquivo
            old_abs_path = os.path.join(root, file)
            shutil.copy2(old_abs_path, new_abs_path) # Usar copy primeiro por segurança
            
            # 2. Atualizar Banco de Dados
            
            # 2.1 Atualizar Statements (Questões)
            # Regex: ![](/storage/old_rel_path)
            old_md_link = f"/storage/{old_rel_path}"
            new_md_link = f"/storage/{new_rel_path}"
            
            cursor.execute("SELECT id, statement FROM questions WHERE statement LIKE ?", (f"%{old_md_link}%",))
            q_rows = cursor.fetchall()
            for q_id, statement in q_rows:
                new_statement = statement.replace(old_md_link, new_md_link)
                cursor.execute("UPDATE questions SET statement = ? WHERE id = ?", (new_statement, q_id))
                updated_questions += 1

            # 2.2 Atualizar Alternativas (image_path)
            cursor.execute("SELECT id FROM question_alternatives WHERE image_path = ?", (old_rel_path,))
            alt_rows = cursor.fetchall()
            for (alt_id,) in alt_rows:
                cursor.execute("UPDATE question_alternatives SET image_path = ? WHERE id = ?", (new_rel_path, alt_id))
                updated_alternatives += 1

            migrated_files += 1

    conn.commit()
    conn.close()

    print(f"Arquivos copiados para a nova estrutura: {migrated_files}")
    print(f"Questões atualizadas (statements): {updated_questions}")
    print(f"Alternativas atualizadas (image_path): {updated_alternatives}")

    # 3. Remover pasta antiga
    try:
        shutil.rmtree(old_base_dir)
        print(f"Pasta legada '{old_base_dir}' removida com sucesso.")
    except Exception as e:
        print(f"Erro ao remover pasta legada: {e}")

    print("\n--- Cleanup Concluído ---")

if __name__ == "__main__":
    final_cleanup()
