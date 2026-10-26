"""Ícones para as lojas, a partir de assets/icon-only.png (gerado por scripts/gerar-imagens.py).

  loja/ios/icone-1024.png      1024×1024, sem transparência (exigência da Apple)
  loja/android/icone-512.png   512×512 (Google Play)
Também converte as capturas de tela em loja/ para RGB (sem canal alfa).
Uso: python scripts/gerar-icones-lojas.py
"""
from pathlib import Path
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
LOJA = RAIZ / "loja"
AZUL = (0, 150, 214)


def sem_alfa(img: Image.Image) -> Image.Image:
    img = img.convert("RGBA")
    fundo = Image.new("RGB", img.size, AZUL)
    fundo.paste(img, mask=img.getchannel("A"))
    return fundo


def main():
    (LOJA / "ios").mkdir(parents=True, exist_ok=True)
    (LOJA / "android").mkdir(parents=True, exist_ok=True)
    icone = sem_alfa(Image.open(RAIZ / "assets" / "icon-only.png"))
    icone.resize((1024, 1024), Image.LANCZOS).save(LOJA / "ios" / "icone-1024.png", optimize=True)
    icone.resize((512, 512), Image.LANCZOS).save(LOJA / "android" / "icone-512.png", optimize=True)
    for png in LOJA.rglob("*.png"):
        img = Image.open(png)
        if img.mode != "RGB":
            img.convert("RGB").save(png, optimize=True)
    print("ok")


if __name__ == "__main__":
    main()
