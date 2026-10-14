ARG PHP_VERSION=8.2
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv \
    && php -n -r 'exit(extension_loaded("openssl") ? 0 : 1);' \
    && python3 -m venv /opt/fixture-venv \
    && /opt/fixture-venv/bin/pip install --disable-pip-version-check cryptography==50.0.1
ENV PATH="/opt/fixture-venv/bin:${PATH}"
WORKDIR /workspace
