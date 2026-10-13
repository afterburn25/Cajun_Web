"""Homepage motion preferences and measured browser evidence; no production speed claim."""
import json

OBSERVER="""(() => {
    window.cwpLayoutEvidence = {supported: PerformanceObserver.supportedEntryTypes.includes('layout-shift'), entries: []};
    if (window.cwpLayoutEvidence.supported) {
        new PerformanceObserver(list => {
            for (const e of list.getEntries()) window.cwpLayoutEvidence.entries.push({value:e.value, startTime:e.startTime, hadRecentInput:e.hadRecentInput});
        }).observe({type:'layout-shift', buffered:true});
    }
})();"""

def run(env,browser):
    check,base,qa=env['check'],env['BASE'],env['QA']
    context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='no-preference')
    context.add_init_script(OBSERVER)
    page=context.new_page();page.goto(base+'/existing/',wait_until='networkidle')
    check('Unrevealed sections start hidden under motion',page.locator('.reveal:not(.shown)').count()>0)
    page.locator('.brand').first.hover()
    check('Brand sheen animates under normal motion',page.evaluate("getComputedStyle(document.querySelector('.brand'),'::after').animationName")=='brand-sheen')
    page.locator('.motion-toggle').dispatch_event('click')
    check('Motion toggle marks the page paused',page.evaluate("document.body.classList.contains('motion-off')") is True)
    check('Motion toggle reports pressed state',page.locator('.motion-toggle').get_attribute('aria-pressed')=='true')
    check('Paused motion force-reveals pending sections',page.evaluate("Array.from(document.querySelectorAll('.reveal:not(.shown)')).every(e=>getComputedStyle(e).opacity==='1')"))
    check('Paused motion disables smooth scrolling',page.evaluate("getComputedStyle(document.documentElement).scrollBehavior")=='auto')
    check('Paused motion stops transitions',page.evaluate("getComputedStyle(document.querySelector('.btn')).transitionDuration")=='0s')
    page.locator('.brand').first.hover()
    check('Paused motion suppresses the brand sheen',page.evaluate("getComputedStyle(document.querySelector('.brand'),'::after').animationName")=='none')
    page.reload(wait_until='networkidle')
    check('Saved pause survives navigation',page.locator('.motion-toggle').get_attribute('aria-pressed')=='true' and page.evaluate("getComputedStyle(document.documentElement).scrollBehavior")=='auto')
    page.locator('.motion-toggle').dispatch_event('click');page.reload(wait_until='networkidle')
    check('Resuming motion survives navigation',page.locator('.motion-toggle').get_attribute('aria-pressed')=='false' and page.evaluate("getComputedStyle(document.documentElement).scrollBehavior")=='smooth')
    metrics=page.evaluate("""(() => {
        const nav=performance.getEntriesByType('navigation')[0]||{}, resources=performance.getEntriesByType('resource');
        const evidence=window.cwpLayoutEvidence;
        let maximum=0, session=0, first=0, previous=0;
        for (const e of evidence.entries) {
            if(e.hadRecentInput) continue;
            if(session && e.startTime-previous<1000 && e.startTime-first<5000) session+=e.value;
            else {session=e.value; first=e.startTime;}
            previous=e.startTime; maximum=Math.max(maximum,session);
        }
        return {scope:'Local Chromium lab navigation until network idle; not field performance or a production guarantee',
            domContentLoadedMs:nav.domContentLoadedEventEnd,loadMs:nav.loadEventEnd,
            resourceCount:resources.length,resourceBytes:resources.reduce((sum,r)=>sum+r.transferSize,0),
            layoutShiftObserverSupported:evidence.supported,layoutShiftEntries:evidence.entries,
            observedSessionWindowCLS:evidence.supported?maximum:null};
    })()""")
    (qa/'motion-metrics.json').write_text(json.dumps(metrics,indent=2)+'\n')
    check('Homepage lab metrics use a real layout-shift observer',metrics['layoutShiftObserverSupported'] and metrics['resourceCount']>0 and metrics['loadMs']>0)
    context.close()
    context=browser.new_context(viewport={'width':390,'height':844},reduced_motion='reduce')
    page=context.new_page();page.goto(base+'/existing/',wait_until='networkidle');page.locator('.brand').first.hover()
    check('Reduced-motion preference suppresses brand animation',page.evaluate("getComputedStyle(document.querySelector('.brand'),'::after').animationName")=='none')
    check('Reduced-motion preference prevents smooth scrolling',page.evaluate("getComputedStyle(document.documentElement).scrollBehavior")=='auto')
    check('Reduced-motion content stays visible',page.evaluate("Array.from(document.querySelectorAll('.reveal')).every(e=>getComputedStyle(e).opacity==='1')"))
    context.close()
    context=browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844},reduced_motion='reduce')
    page=context.new_page();page.goto(base+'/existing/',wait_until='networkidle')
    # Reveal classes are added by JavaScript. Inspect the actual server-rendered
    # content, not an enhancement class which correctly does not exist here.
    content=page.locator('.hero h1,.section-head,.service-card')
    check('Homepage remains readable without JavaScript',content.count()>3 and content.evaluate_all("items=>items.every(e=>getComputedStyle(e).opacity==='1' && e.getBoundingClientRect().height>0)"))
    page.locator('.hero .button-row a').first.click()
    check('Homepage work link navigates without JavaScript','page=work' in page.url and page.locator('.project-card').count()>0)
    context.close()
