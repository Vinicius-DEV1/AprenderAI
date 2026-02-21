import sqlite3
import os

db_path = r'd:\Programming\EMPRESA - STACKUP SOFTWARE\Projetos\AprovadoAI\database\database.sqlite'

if not os.path.exists(db_path):
    print(f"Database not found at {db_path}")
    exit(1)

conn = sqlite3.connect(db_path)
cursor = conn.cursor()

print("--- Questions still using OLD paths ---")
cursor.execute("SELECT id, external_id, statement FROM questions WHERE statement LIKE '%/storage/enem/%' LIMIT 10;")
rows = cursor.fetchall()
for row in rows:
    print(f"ID: {row[0]}")
    print(f"External ID: {row[1]}")
    print(f"Statement: {row[2][:100]}...")
    print("-" * 20)

conn.close()
