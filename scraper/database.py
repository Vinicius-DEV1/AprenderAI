import sqlite3
import os
from datetime import datetime
import config
from utils import log
from colorama import Fore

def setup_db(banca_nome):
    banca_dir = os.path.join(config.BASE_DIR, banca_nome)
    if not os.path.exists(banca_dir):
        os.makedirs(banca_dir)
        
    db_path = os.path.join(banca_dir, f"banco_{banca_nome.lower()}.db")
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS exams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization TEXT,
        role TEXT,
        year TEXT,
        institution TEXT,
        origin TEXT,
        source_url TEXT UNIQUE,
        extracted_at TEXT
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        exam_id INTEGER,
        number TEXT,
        tipo_questao TEXT,
        arquivo_origem TEXT,
        statement TEXT,
        alternatives TEXT,
        correct_answer TEXT,
        discursive_answer TEXT,
        pdf_page INTEGER,
        image_path TEXT,
        review_status TEXT DEFAULT 'pending',
        FOREIGN KEY (exam_id) REFERENCES exams (id)
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE
    )''')
    
    cursor.execute('''CREATE TABLE IF NOT EXISTS question_subject (
        question_id INTEGER,
        subject_id INTEGER,
        PRIMARY KEY (question_id, subject_id),
        FOREIGN KEY (question_id) REFERENCES questions (id),
        FOREIGN KEY (subject_id) REFERENCES subjects (id)
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS topics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS question_topic (
        question_id INTEGER,
        topic_id INTEGER,
        PRIMARY KEY (question_id, topic_id),
        FOREIGN KEY (question_id) REFERENCES questions (id),
        FOREIGN KEY (topic_id) REFERENCES topics (id)
    )''')

    cursor.execute('''CREATE TABLE IF NOT EXISTS failed_scrapes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT UNIQUE,
        error_message TEXT,
        attempted_at TEXT
    )''')
        
    conn.commit()
    return conn

def registrar_falha_no_banco(conn, url, motivo):
    try:
        cursor = conn.cursor()
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("INSERT OR REPLACE INTO failed_scrapes (url, error_message, attempted_at) VALUES (?,?,?)", 
                       (url, motivo, timestamp))
        conn.commit()
    except Exception as e:
        log(f"Erro ao registar falha no log interno: {e}", Fore.RED)