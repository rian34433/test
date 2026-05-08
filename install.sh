#!/bin/bash

# ==========================================================================
# Script Instalasi Otomatis SJ Web App untuk Armbian (STB HG680P)
# ==========================================================================

# Warna untuk output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=====================================================${NC}"
echo -e "${GREEN}    Memulai Instalasi Otomatis SJ Web App${NC}"
echo -e "${YELLOW}=====================================================${NC}"

# 1. Cek apakah dijalankan sebagai root
if [ "$EUID" -ne 0 ]; then 
  echo -e "${RED}Harap jalankan script ini dengan sudo!${NC}"
  exit 1
fi

# 2. Update sistem dan install dependencies
echo -e "\n${YELLOW}[1/5] Menginstall Nginx, PHP-FPM, dan dependencies...${NC}"
apt update
apt install -y nginx php-fpm php-json php-common

# Mendapatkan versi PHP yang terinstall
PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
echo -e "${GREEN}PHP versi $PHP_VERSION terdeteksi.${NC}"

# 3. Menyiapkan direktori web
WEB_DIR="/var/www/html/SJ"
echo -e "\n${YELLOW}[2/5] Menyiapkan direktori di $WEB_DIR...${NC}"
mkdir -p "$WEB_DIR"

# Menyalin file dari direktori saat ini ke direktori web
# Asumsi script dijalankan dari folder proyek
echo -e "Menyalin file aplikasi..."
cp index.html "$WEB_DIR/"
cp api.php "$WEB_DIR/"

# Proteksi database.json agar tidak menimpa data yang sudah ada
if [ -f "$WEB_DIR/database.json" ]; then
    echo -e "${YELLOW}Peringatan: $WEB_DIR/database.json sudah ada. Melewati penyalinan database untuk melindungi data lama.${NC}"
    # Opsional: buat backup
    cp "$WEB_DIR/database.json" "$WEB_DIR/database.json.bak_$(date +%Y%m%d_%H%M%S)"
    echo -e "${GREEN}Backup database lama dibuat.${NC}"
else
    echo -e "Menyalin database awal..."
    cp database.json "$WEB_DIR/"
fi

# 4. Mengatur Permission
echo -e "\n${YELLOW}[3/5] Mengatur permission file...${NC}"
chown -R www-data:www-data "$WEB_DIR"
find "$WEB_DIR" -type d -exec chmod 755 {} \;
find "$WEB_DIR" -type f -exec chmod 644 {} \;
# Khusus database.json perlu akses tulis lebih terbuka untuk group
chmod 664 "$WEB_DIR/database.json"

# 5. Konfigurasi Nginx
echo -e "\n${YELLOW}[4/5] Mengonfigurasi Nginx...${NC}"
NGINX_CONF="/etc/nginx/sites-available/sj_app"

cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    server_name _;
    root $WEB_DIR;
    index index.html;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
    }

    # Keamanan: Jangan biarkan file database diakses langsung
    location = /database.json {
        deny all;
    }

    # Optimasi cache untuk file statis
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
EOF

# Aktifkan konfigurasi dan hapus default jika ada
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test dan Restart Nginx
nginx -t && systemctl restart nginx
systemctl restart php$PHP_VERSION-fpm

# 6. Selesai
IP_ADDR=$(hostname -I | cut -d " " -f 1)
echo -e "\n${YELLOW}=====================================================${NC}"
echo -e "${GREEN}       INSTALASI SELESAI!${NC}"
echo -e "${YELLOW}=====================================================${NC}"
echo -e "Aplikasi dapat diakses di: ${GREEN}http://$IP_ADDR${NC}"
echo -e "Folder aplikasi: ${YELLOW}$WEB_DIR${NC}"
echo -e "Database: ${YELLOW}$WEB_DIR/database.json${NC}"
echo -e "PHP-FPM Socket: ${YELLOW}/run/php/php$PHP_VERSION-fpm.sock${NC}"
echo -e "${YELLOW}=====================================================${NC}"
