import sqlite3
import json

db_path = 'banco_provas_completo.db'
output_file = 'schema_output_py.txt'

try:
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    with open(output_file, 'w', encoding='utf-8') as f:
        # Get list of tables
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")
        tables = cursor.fetchall()
        
        f.write("--- DATABASE INSPECTION ---\n\n")
        
        for table_name in tables:
            table = table_name[0]
            f.write(f"TABLE: {table}\n")
            f.write("-" * 50 + "\n")
            
            # Get schema
            cursor.execute(f"SELECT sql FROM sqlite_master WHERE type='table' AND name='{table}';")
            schema = cursor.fetchone()[0]
            f.write(f"Schema:\n{schema}\n\n")
            
            # Get row count
            cursor.execute(f'SELECT COUNT(*) FROM "{table}";')
            count = cursor.fetchone()[0]
            f.write(f"Row Count: {count}\n")
            
            # Get sample data (first 3 rows)
            f.write("Sample Data:\n")
            cursor.execute(f'SELECT * FROM "{table}" LIMIT 3;')
            rows = cursor.fetchall()
            
            # Get column names
            cursor.execute(f'PRAGMA table_info("{table}");')
            columns = [col[1] for col in cursor.fetchall()]
            f.write(f"Columns: {', '.join(columns)}\n")
            
            for row in rows:
                data = dict(zip(columns, row))
                f.write(json.dumps(data, indent=2, ensure_ascii=False) + "\n")
            
            f.write("\n" + "=" * 50 + "\n\n")
            
    print(f"Inspection complete. Output written to {output_file}")
    conn.close()

except Exception as e:
    print(f"Error: {e}")
