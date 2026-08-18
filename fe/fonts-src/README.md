# Font sources — not served

The licensed OTFs the web fonts are built from. They live here rather than in
`public/` because everything under `public/` is uploaded to the host verbatim:
left there, the full desktop family — all 12 weights, 1.2 MB — would have been
downloadable from `https://…/fonts/NeoSansPro/NeoSansProBold.OTF` by anyone.

To rebuild `public/fonts/*.woff2` after replacing a source file:

```sh
pip install fonttools brotli
python - <<'PY'
from fontTools.ttLib import TTFont
for src, dst in [('Regular', 'Regular'), ('Medium', 'Medium'), ('Bold', 'Bold')]:
    f = TTFont(f'fe/fonts-src/NeoSansPro-otf/NeoSansPro{src}.OTF')
    f.flavor = 'woff2'
    f.save(f'fe/public/fonts/NeoSansPro-{dst}.woff2')
PY
```

Note the character set: this family covers Latin-1 and part of Latin Extended-A
only. It has 2 of the 90 codepoints in U+1EA0–U+1EF9 and none of ơ ư, so it
cannot set Vietnamese text — see the comment above the `@font-face` rules in
`app/assets/css/main.css` for how that is handled.
