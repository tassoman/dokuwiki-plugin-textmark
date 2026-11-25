# textmark

Watermarks images on the fly when they are served through DokuWiki's media
dispatcher. Nothing is written back to the original file: the watermarked
version is cached and served instead.

## Requirements

- PHP 8.1 or newer
- Either the GD extension with `imagettftext()` support, or ImageMagick
  (`$conf['im_convert']` pointing at a `convert` binary). ImageMagick is
  preferred when available; GD is used as a fallback.

## Configuration

All settings live under *Administration → Configuration Settings → textmark*.

| Setting | Default | Meaning |
| --- | --- | --- |
| `watermark_text` | *(empty)* | Text to render. When empty, `©{year} {wiki title}` is generated at runtime, falling back to the base URL (or bare hostname) when no title is set. |
| `font_file` | *(empty)* | Font to render with. See [Fonts](#fonts). Empty uses the bundled `fonts/default.ttf`. |
| `font_size` | `5` | Font size as a percentage (1–100) of the image's **shorter** side, so the watermark scales with the picture. |
| `min_font_size` | `8` | Lower bound in points. A percentage that would render smaller is raised to this value. |
| `text_color` | `#FFFFFF` | Hex colour of the text. |
| `text_opacity` | `50` | 0 (invisible) to 100 (solid). Applied to the finished watermark as a whole, so the picture shows through it rather than the text turning grey. |
| `position` | `bottom-right` | `top-left`, `top-right`, `bottom-left`, `bottom-right` or `center`. |
| `angle` | `0` | Rotation in degrees. Negative input is normalised to `360 - value`. |
| `border` | `1` | Draw a border around the text so it stays readable on any background. |
| `border_color` | `#000000` | Hex colour of the border. |
| `border_width` | `2` | Border thickness in pixels (0–50). |
| `padding` | `15` | Margin from the image edges, in pixels. |
| `min_width` | `200` | Images narrower than this are served untouched. |
| `exclude_namespaces` | `wiki,playground` | Comma-separated media namespaces to skip. |

### Fonts

`font_file` accepts three forms:

1. **A media manager id** — upload the font through the media manager, then
   enter the id it shows, e.g. `fonts:myfont.ttf`. A slash-separated value
   (`fonts/myfont.ttf`) is accepted too.
2. **A path relative to the plugin** — e.g. `fonts/myfont.ttf`.
3. **An absolute path**, but only when it resolves inside the DokuWiki tree or
   the plugin directory.

Anything else is rejected and the bundled font is used instead. The file must
have a `.ttf` or `.otf` extension and a valid font signature; the value is
never evaluated as code.

#### Allowing font uploads

DokuWiki's stock `conf/mime.conf` has no font types, so the media manager
rejects `.ttf` and `.otf` uploads out of the box. Add them to
`conf/mime.local.conf` (create the file if needed):

```
ttf    font/ttf
otf    font/otf
```

Without this, uploads fail with *"File is not a supported file type"*. The
plugin itself does not need the entry — it only affects uploading.

#### Bundled font

`fonts/default.ttf` is a placeholder. Replace it with any font you are licensed
to redistribute. Fonts under the SIL Open Font License 1.1 (Noto Sans, DejaVu
Sans, Liberation Sans, Carlito) are compatible with this plugin's AGPL-3.0
licensing; keep the font's own licence file next to it.

## How it works

`action.php` hooks `MEDIA_SENDFILE` and, for `jpg`/`jpeg`/`png`/`gif` files
that pass the namespace and width filters, renders a watermarked copy into the
cache and points the event at it. The cache key covers the source file's
modification time, the resolved text, the resolved font path and its
modification time, and every setting that affects the output — so changing any
of them produces a fresh render.

Both renderers paint the text and its border onto a separate translucent layer
and composite that layer onto the picture once. Drawing directly onto the image
would blend each pass against the previous one — the text against its own
border — which makes translucent text look grey and opaque instead of letting
the picture show through.

Rendering is delegated to `classes/`:

- `Watermarker` — abstract base: config normalisation, font sizing, colour
  validation, angle normalisation.
- `ImageMagickWatermarker` — renders through the `convert` binary.
- `GdWatermarker` — renders through GD's `imagettftext()`.
- `FontResolver` — validates and resolves `font_file`.

## Licence

AGPL-3.0.
