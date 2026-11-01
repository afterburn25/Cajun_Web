"""Check current accessible navigation names AND their same-origin controller targets."""
from urllib.parse import urljoin, urlsplit, parse_qs
DESTINATIONS=(('Overview','dashboard'),('Service catalog','catalog'),('20i workspace','provider-settings'),('Administrators','staff'))
def core_admin_destinations(page):
    nav=page.locator('[data-admin-navigation]')
    if nav.count()!=1:return False
    base=urlsplit(page.url)
    for label,view in DESTINATIONS:
        link=nav.get_by_role('link',name=label,exact=True,include_hidden=True)
        if link.count()!=1:return False
        href=link.get_attribute('href')
        if not isinstance(href,str) or not href:return False
        try:target=urlsplit(urljoin(page.url,href))
        except ValueError:return False
        if (target.scheme,target.netloc,target.path)!=(base.scheme,base.netloc,base.path) or target.fragment or parse_qs(target.query,keep_blank_values=True)!={'view':[view]}:return False
    return True
