#!/bin/zsh
SCRIPT_DIR="${0:A:h}"

[ -f ~/.myaliases ] && . ~/.myaliases
[ -f ~/.myfunctions ] && . ~/.myfunctions

# Load .env from script directory if present
[ -f "$SCRIPT_DIR/.env" ] && set -a && source "$SCRIPT_DIR/.env" && set +a

if [ -z "$FTP_USER" ] || [ -z "$FTP_PASS" ] || [ -z "$FTP_HOST" ]; then
  echo "Error: FTP_USER, FTP_PASS, and FTP_HOST must be set (or add them to $SCRIPT_DIR/.env)" >&2
  exit 1
fi

cd "$SCRIPT_DIR"
lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "set ssl:verify-certificate no; cd dersalvador; put ai-devops-landing.html -o index.html; put contact.php; quit"
