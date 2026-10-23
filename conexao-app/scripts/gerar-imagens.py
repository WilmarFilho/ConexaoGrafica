"""Gera as imagens da marca usadas pelo app (ícones, abertura, logos).

Fonte: logos do tema da Conexão Editora (conexao-editora/wp-content/themes/conexao-editora/assets/img).
Saídas:
  assets/                 fontes para `npx @capacitor/assets generate` (ícone e splash do Android/iOS)
  public/icons/           ícones do PWA
  src/assets/marca/       logos usados dentro do app
Uso: python scripts/gerar-imagens.py
"""
from pathlib import Path
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
TEMA = RAIZ.parent / "conexao-editora" / "wp-content" / "themes" / "conexao-editora" / "assets" / "img"
AZUL = (0, 150, 214, 255)  # #0096D6

def branca(img: Image.Image) -> Image.Image:
    """Mantém só a forma (alfa) e pinta de branco."""
    img = img.convert("RGBA")
    alfa = img.getchannel("A")
    out = Image.new("RGBA", img.size, (255, 255, 255, 0))
    out.putalpha(alfa)
    return out

def marca_do_favicon() -> Image.Image:
    """O favicon é a marca branca sobre um quadrado azul; tira só a parte branca."""
    fav = Image.open(TEMA / "favicon.png").convert("RGBA")
    px = fav.load()
    out = Image.new("RGBA", fav.size, (255, 255, 255, 0))
    po = out.load()
    for y in range(fav.height):
        for x in range(fav.width):
            r, g, b, a = px[x, y]
            # branco puro = 255; azul da marca tem r≈0. Usa o vermelho como medida de "brancura".
            if a > 0 and r > 10:
                po[x, y] = (255, 255, 255, int(a * min(1.0, r / 255)))
    return out.crop(out.getbbox())

def centralizar(fundo: Image.Image, img: Image.Image, largura_rel: float) -> Image.Image:
    w = int(fundo.width * largura_rel)
    h = int(img.height * w / img.width)
    if h > fundo.height * largura_rel:
        h = int(fundo.height * largura_rel)
        w = int(img.width * h / img.height)
    peca = img.resize((w, h), Image.LANCZOS)
    fundo.alpha_composite(peca, ((fundo.width - w) // 2, (fundo.height - h) // 2))
    return fundo

def main():
    (RAIZ / "assets").mkdir(exist_ok=True)
    (RAIZ / "public" / "icons").mkdir(parents=True, exist_ok=True)
    (RAIZ / "src" / "assets" / "marca").mkdir(parents=True, exist_ok=True)

    marca = marca_do_favicon()
    logo_vertical = Image.open(TEMA / "logo-rodape.png").convert("RGBA")
    logo_horizontal = Image.open(TEMA / "logo.png").convert("RGBA")

    # Ícone completo (quadrado azul cheio; o sistema arredonda)
    icone = centralizar(Image.new("RGBA", (1024, 1024), AZUL), marca, 0.62)
    icone.save(RAIZ / "assets" / "icon-only.png")
    # Ícone adaptativo do Android: frente (marca) e fundo (azul). Zona segura = 66% do centro.
    centralizar(Image.new("RGBA", (1024, 1024), (0, 0, 0, 0)), marca, 0.44).save(RAIZ / "assets" / "icon-foreground.png")
    Image.new("RGBA", (1024, 1024), AZUL).save(RAIZ / "assets" / "icon-background.png")
    # Abertura (splash)
    for nome in ("splash.png", "splash-dark.png"):
        centralizar(Image.new("RGBA", (2732, 2732), AZUL), branca(logo_vertical), 0.26).save(RAIZ / "assets" / nome)

    # PWA
    for tam in (192, 512):
        icone.resize((tam, tam), Image.LANCZOS).save(RAIZ / "public" / "icons" / f"icone-{tam}.png")
    mascaravel = centralizar(Image.new("RGBA", (512, 512), AZUL), marca, 0.5)
    mascaravel.save(RAIZ / "public" / "icons" / "icone-mascaravel-512.png")
    icone.resize((180, 180), Image.LANCZOS).convert("RGB").save(RAIZ / "public" / "icons" / "apple-touch-icon.png")
    icone.resize((64, 64), Image.LANCZOS).save(RAIZ / "public" / "icons" / "favicon-64.png")

    # Logos dentro do app
    def reduzir(img, largura):
        return img.resize((largura, int(img.height * largura / img.width)), Image.LANCZOS)
    reduzir(logo_horizontal, 560).save(RAIZ / "src" / "assets" / "marca" / "logo.png", optimize=True)
    reduzir(branca(logo_horizontal), 560).save(RAIZ / "src" / "assets" / "marca" / "logo-branco.png", optimize=True)
    reduzir(branca(logo_vertical), 360).save(RAIZ / "src" / "assets" / "marca" / "logo-vertical-branco.png", optimize=True)
    print("ok")

if __name__ == "__main__":
    main()
