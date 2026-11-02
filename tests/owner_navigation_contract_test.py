"""Strict locator doubles for the shared assertion; installed Chromium runs separately."""
import copy,json
from owner_navigation_contract import DESTINATIONS,core_admin_destinations
class Link:
    def __init__(self,values):self.values=values
    def count(self):return len(self.values)
    def get_attribute(self,name):
        assert name=='href' and len(self.values)==1
        return self.values[0]
class Page:
    url='http://127.0.0.1:8091/existing/admin/index.php?view=dashboard'
    def __init__(self):self.links={name:['?view='+view] for name,view in DESTINATIONS};self.navs=1
    def locator(self,selector):assert selector=='[data-admin-navigation]';return self
    def count(self):return self.navs
    def get_by_role(self,role,**kwargs):
        assert role=='link' and kwargs['exact'] is True and kwargs['include_hidden'] is True
        return Link(self.links.get(kwargs['name'],[]))
checks=[]
def check(name,passed):
    checks.append({'name':name,'passed':bool(passed)})
    if not passed:raise AssertionError(name)
check('Current labels and precise controller targets are accepted',core_admin_destinations(Page()))
for count in [0,2]:
    p=Page();p.navs=count;check('Missing or duplicate administration region is refused '+str(count),not core_admin_destinations(p))
for label,view in DESTINATIONS:
    for name,values in [('missing',[]),('duplicate',['?view='+view]*2),('missing href',[None]),('blank href',['']),('wrong view',['?view=login']),('foreign host',['https://example.invalid/admin/index.php?view='+view]),('wrong path',['/other/index.php?view='+view]),('extra selector',['?view='+view+'&support=unreviewed']),('duplicate selector',['?view='+view+'&view=login']),('fragment',['?view='+view+'#fragment'])]:
        p=Page();p.links[label]=values;check(label+' rejects '+name,not core_admin_destinations(p))
p=Page();p.links['20i connection']=p.links.pop('20i workspace');check('Obsolete accessible label cannot substitute for current workspace',not core_admin_destinations(p))
print(json.dumps({'passed':True,'scope':'Shared navigation assertion with strict locator doubles, not installed browser acceptance','checks':checks},indent=2))
