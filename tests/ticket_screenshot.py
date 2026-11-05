"""Capture authenticated ticket pages after the real activity bell has settled."""
import struct
from playwright.sync_api import expect


def capture_ticket_page(page, path):
    page.bring_to_front()
    # This fixture has an authenticated, authorized activity response. Its public
    # bell adds a row after fetch completes; measuring earlier clips the footer.
    expect(page.locator('[data-activity-bell] .activity-bell')).to_be_visible()
    page.evaluate('async () => { await document.fonts.ready; }')
    png = page.screenshot(path=str(path), full_page=True)
    assert png[:8] == b'\x89PNG\r\n\x1a\n'
    width, height = struct.unpack('>II', png[16:24])
    layout = page.evaluate("""() => ({
        width: document.documentElement.scrollWidth,
        height: document.documentElement.scrollHeight,
        scale: devicePixelRatio,
        footer: document.querySelector('.site-footer, .admin-footer')
            .getBoundingClientRect().bottom + scrollY
    })""")
    # All ticket fixtures use scale 1. A CSS boundary can extend fractionally
    # beyond scrollHeight due to browser rounding, hence the one-pixel tolerance.
    return (layout['scale'] == 1 and width == layout['width']
            and height == layout['height'] and layout['footer'] <= height + 1)
