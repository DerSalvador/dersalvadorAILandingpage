#!/bin/zsh
SCRIPT_DIR="${0:A:h}"
cd "$SCRIPT_DIR"

. ./.env
[ -f ~/.myaliases ] && . ~/.myaliases
[ -f ~/.myfunctions ] && . ~/.myfunctions

if [ -z "$FTP_USER" ] || [ -z "$FTP_PASS" ] || [ -z "$FTP_HOST" ]; then
  echo "Error: FTP_USER, FTP_PASS, and FTP_HOST must be set (create .env)" >&2
  exit 1
fi

lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "set ssl:verify-certificate no; cd dersalvador; put ai-devops-landing.html -o index.html; put contact.php; put .env; quit"
