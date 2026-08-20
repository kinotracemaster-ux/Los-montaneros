# PHP 8.3 CLI + Python 3 (pdfminer.six) runtime for Los Montañeros.
#
# The app is a PHP application (api.php serves JSON, index.html the UI) that
# shells out to `python3 pdf_search.py` for PDF text search. It therefore needs
# BOTH PHP and Python available at runtime. A dedicated Dockerfile is used
# instead of Nixpacks because the root requirements.txt makes Nixpacks
# auto-detect this as a Python-only project, producing an image without `php`
# (the "php: command not found" restart loop seen in the deploy logs).
FROM php:8.3-cli

# System deps: libzip for the PHP zip extension, python3 + pip for pdf_search.py.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        python3 \
        python3-pip \
    && docker-php-ext-install pdo_mysql zip \
    && pip3 install --no-cache-dir --break-system-packages pdfminer.six \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app
RUN chmod +x /app/start.sh

# Railway provides $PORT at runtime; start.sh expands it inside a shell and
# falls back to 8080 for local runs. Using an explicit script guarantees the
# variable is expanded regardless of how the platform invokes the command.
CMD ["sh", "/app/start.sh"]
