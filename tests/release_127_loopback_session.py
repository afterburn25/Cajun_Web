"""Requests session whose logical URL and actual transport stay on one loopback port."""
import os
from unittest.mock import patch
from urllib.parse import urlsplit

import requests


class LocalSession(requests.Session):
    def __init__(self, port):
        if type(port) is not int or not 1 <= port <= 65535:
            raise RuntimeError('Invalid loopback port')
        super().__init__()
        self.allowed_port = port
        self.trust_env = False
        self.assert_proxy_isolation()

    @classmethod
    def from_origin(cls, origin):
        parsed = urlsplit(origin)
        if (parsed.scheme != 'http' or parsed.hostname != '127.0.0.1'
                or parsed.port is None or parsed.username is not None or parsed.password is not None
                or parsed.path or parsed.query or parsed.fragment):
            raise RuntimeError('Invalid loopback origin')
        return cls(parsed.port)

    def assert_proxy_isolation(self):
        hostile = {
            'HTTP_PROXY': 'http://proxy.invalid:8080',
            'HTTPS_PROXY': 'http://proxy.invalid:8080',
            'ALL_PROXY': 'http://proxy.invalid:8080',
            'NO_PROXY': '',
        }
        with patch.dict(os.environ, hostile, clear=False):
            settings = self.merge_environment_settings(
                f'http://127.0.0.1:{self.allowed_port}/proxy-boundary', {}, None, None, None)
        if self.trust_env or settings.get('proxies'):
            raise RuntimeError('Ambient proxy isolation failed')

    def send(self, request, **kwargs):
        parsed = urlsplit(request.url)
        if (parsed.scheme != 'http' or parsed.hostname != '127.0.0.1'
                or parsed.port != self.allowed_port or parsed.username is not None
                or parsed.password is not None):
            raise RuntimeError('Non-loopback or cross-port request refused')
        if kwargs.get('proxies'):
            raise RuntimeError('Explicit proxy request refused')
        return super().send(request, **kwargs)
