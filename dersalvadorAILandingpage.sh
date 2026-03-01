#!/bin/zsh
cd ~/dersalvador/openclaw-deepseek
[ -f ~/.myaliases ] && . ~/.myaliases
[ -f ~/.myfunctions ] && . ~/.myfunctions

usage() {
  echo "Usage: ${(%):-%N} param1" 2>&1 && exit 1
}
# [ -z "$1" ] && usage
cd ~

if [ -z "$FTP_USER" ] || [ -z "$FTP_PASS" ] || [ -z "$FTP_HOST" ]; then
  echo "Error: FTP_USER, FTP_PASS, and FTP_HOST must be set" >&2
  exit 1
fi

lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "set ssl:verify-certificate no; cd dersalvador; put ai-devops-landing.html -o index.html; put contact.php; quit"
