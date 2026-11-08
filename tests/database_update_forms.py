"""Repeated-control helpers for the reviewed database-update forms."""
from urllib.parse import urljoin


def form_pairs(form):
    """Collect successful controls without collapsing repeated [] names."""
    pairs=[]
    for node in form.select('input[name],textarea[name],select[name]'):
        if node.get('type') in ('file','submit','button'):continue
        if node.get('type') in ('checkbox','radio') and not node.has_attr('checked'):continue
        if node.name=='textarea':values=[node.text]
        elif node.name=='select':
            selected=node.select('option[selected]') or ([] if node.has_attr('multiple') else node.select('option')[:1])
            values=[option.get('value',option.text) for option in selected]
        else:values=[node.get('value','')]
        pairs.extend((node['name'],value) for value in values)
    return pairs


def database_plan(session,url,markers,soup,form_for):
    page=session.get(url,timeout=30);form=form_for(page,'studio_database_plan')
    data=[pair for pair in form_pairs(form) if pair[0]!='database_updates[]']
    data.extend(('database_updates[]',marker) for marker in markers)
    response=session.post(urljoin(url,form.get('action') or url),data=data,allow_redirects=False,timeout=30)
    location=urljoin(url,response.headers.get('Location',''))
    review=session.get(location,timeout=30) if response.status_code==303 else response
    return response,location,review


def database_apply(session,location,review,form_for,confirm=True,overrides=None):
    form=form_for(review,'studio_database_apply');data=form_pairs(form)
    if confirm:data.append(('confirm','1'))
    for name,value in (overrides or {}).items():
        data=[pair for pair in data if pair[0]!=name]
        if value is not None:data.append((name,value))
    return session.post(urljoin(location,form.get('action') or location),data=data,timeout=90)
