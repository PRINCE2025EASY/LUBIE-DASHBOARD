// Render every page of a .drawio file to PNG with mxGraph 4.2.2 in headless Chromium.
// Approximate preview: draw.io-only shapes and ER markers fall back to basic shapes.
// Usage: node render.js file.drawio outdir
const { chromium } = require('playwright');
const fs = require('fs'); const path = require('path');
(async () => {
  const [file, outdir] = process.argv.slice(2);
  fs.mkdirSync(outdir, { recursive: true });
  const xml = fs.readFileSync(file, 'utf8');
  const mx = fs.readFileSync(path.join(__dirname, 'node_modules/mxgraph/javascript/mxClient.min.js'), 'utf8');
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 900 } });
  await page.setContent('<html><body style="margin:0;background:#fff"><div id="c"></div></body></html>');
  await page.addScriptTag({ content: 'window.mxBasePath="";window.mxLoadResources=false;window.mxLoadStylesheet=false;window.mxImageBasePath="";' });
  await page.addScriptTag({ content: mx });
  const names = await page.evaluate((xml) => {
    const doc = mxUtils.parseXml(xml);
    return Array.from(doc.getElementsByTagName('diagram')).map(d => d.getAttribute('name'));
  }, xml);
  const base = path.basename(file, '.drawio');
  for (let i = 0; i < names.length; i++) {
    const size = await page.evaluate(({ xml, i }) => {
      const c = document.getElementById('c'); c.innerHTML = '';
      const doc = mxUtils.parseXml(xml);
      const d = doc.getElementsByTagName('diagram')[i];
      const model = mxUtils.parseXml(mxUtils.getXml(d.getElementsByTagName('mxGraphModel')[0])).documentElement;
      const graph = new mxGraph(c);
      graph.setHtmlLabels(true);
      const ss = graph.getStylesheet();
      const dec = new mxCodec(model.ownerDocument);
      dec.decode(model, graph.getModel());
      graph.refresh();
      const b = graph.getGraphBounds();
      c.style.width = (b.x + b.width + 40) + 'px'; c.style.height = (b.y + b.height + 40) + 'px';
      return { w: Math.ceil(b.x + b.width + 40), h: Math.ceil(b.y + b.height + 40) };
    }, { xml, i });
    await page.setViewportSize({ width: Math.max(800, size.w), height: Math.max(600, size.h) });
    const safe = names[i].replace(/[^A-Za-z0-9]+/g, '_');
    const out = path.join(outdir, `${base}__p${String(i + 1).padStart(2, '0')}_${safe}.png`);
    await page.locator('#c').screenshot({ path: out });
    console.log(out, size.w + 'x' + size.h);
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
