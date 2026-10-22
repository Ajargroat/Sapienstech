{{-- MathJax 3 — config MUST come before the library loads. Byte-for-byte
     behavioural match with the reference system
     (E:\ocr-quest\ui\index.html:11-23): inline `$…$`, display `$$…$$`,
     processEscapes (Persian text may contain a literal `$`), and the
     no-mathjax ignore class as the escape hatch for raw dollars. --}}
<script nonce="{{ csp_nonce() }}">
window.MathJax = {
  tex: {
    inlineMath: [['$', '$'], ['\\(', '\\)']],
    displayMath: [['$$', '$$'], ['\\[', '\\]']],
    processEscapes: true
  },
  options: { ignoreHtmlClass: 'no-mathjax' },
  svg: { fontCache: 'global' }
};
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
