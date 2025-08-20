#!/usr/bin/env python3.11
"""Envia config.xml do pfSense por e-mail.
Autoria: Thiago Motta Massensini (2025) - MIT
Edite as variáveis antes de executar em produção.
"""
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.application import MIMEApplication
import socket
import os
import sys

SENDER = os.environ.get("PFS_BACKUP_SENDER", "remetente@dominio.com.br")
SENDER_PASSWORD = os.environ.get("PFS_BACKUP_PASS", "SENHA")
RECEIVER = os.environ.get("PFS_BACKUP_RCPT", "destinatario@dominio.com.br")
SMTP_SERVER = os.environ.get("PFS_BACKUP_SMTP", "smtp.dominio.com.br")
SMTP_PORT = int(os.environ.get("PFS_BACKUP_PORT", "465"))
CONFIG_FILE = "/cf/conf/config.xml"

hostname = socket.gethostname()
subject = f"{hostname} - Backup Config XML"

def main():
    if not os.path.isfile(CONFIG_FILE):
        print(f"ERRO: Arquivo não encontrado: {CONFIG_FILE}", file=sys.stderr)
        sys.exit(2)

    msg = MIMEMultipart()
    msg["From"] = SENDER
    msg["To"] = RECEIVER
    msg["Subject"] = subject

    with open(CONFIG_FILE, "rb") as f:
        part = MIMEApplication(f.read(), _subtype="xml")
    part.add_header("Content-Disposition", "attachment", filename="config.xml")
    msg.attach(part)

    with smtplib.SMTP_SSL(SMTP_SERVER, SMTP_PORT) as srv:
        srv.login(SENDER, SENDER_PASSWORD)
        srv.sendmail(SENDER, RECEIVER, msg.as_string())

    print("Backup enviado com sucesso.")

if __name__ == "__main__":
    main()
