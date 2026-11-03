FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git nodejs python3 python3-venv ca-certificates libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql \
    && (php -r 'exit(extension_loaded("curl") ? 0 : 1);' || docker-php-ext-install curl) \
    && rm -rf /var/lib/apt/lists/*
ENV PLAYWRIGHT_BROWSERS_PATH=/opt/php-ui-browsers
ENV PYTHONDONTWRITEBYTECODE=1
RUN python3 -m venv /opt/php-ui-venv \
    && /opt/php-ui-venv/bin/pip install --no-cache-dir requests beautifulsoup4 cryptography==50.0.1 playwright \
    && /opt/php-ui-venv/bin/python -m playwright install --with-deps chromium \
    && php -r 'foreach (["curl","openssl","pdo_mysql"] as $module) { if (!extension_loaded($module)) { throw new RuntimeException("Missing required PHP module: ".$module); } }'
ENV PATH=/opt/php-ui-venv/bin:$PATH
WORKDIR /workspace
