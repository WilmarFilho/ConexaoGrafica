"""Gera public/mock/amostra.pdf: livro de DEMONSTRAÇÃO usado só no modo de exemplo (VITE_MOCK=1).

Texto original escrito para o teste (não é obra de terceiros). Tem sumário (outline) com
capítulos e seções, texto pesquisável e algumas figuras coloridas para testar os temas do leitor.
Uso: python scripts/gerar-amostra-pdf.py            → public/mock/amostra.pdf (marcado como AMOSTRA)
     python scripts/gerar-amostra-pdf.py --vitrine  → public/mock/vitrine.pdf (sem as marcas, para as capturas de tela das lojas)
"""
import math
import random
import sys
from pathlib import Path

VITRINE = "--vitrine" in sys.argv

RAIZ = Path(__file__).resolve().parent.parent
SAIDA = RAIZ / "public" / "mock" / ("vitrine.pdf" if VITRINE else "amostra.pdf")
L, A = 420, 595  # A5 em pontos
MARGEM = 46

FRASES = [
    "A leitura digital mudou a forma como estudantes e profissionais consultam livros técnicos.",
    "Um bom leitor de PDF precisa respeitar o desenho original da página, com figuras e tabelas no lugar certo.",
    "Nesta amostra, cada capítulo traz parágrafos curtos para testar a busca dentro do livro.",
    "O marcador de página ajuda a voltar rapidamente a um trecho importante.",
    "A Conexão Editora publica obras de saúde, direito, educação e literatura.",
    "Quem estuda ultrassonografia costuma alternar entre o texto e as imagens de referência.",
    "O progresso de leitura é salvo no aparelho e sincronizado com a sua conta quando há internet.",
    "Livros baixados continuam disponíveis mesmo sem conexão, por exemplo durante uma viagem.",
    "A marca d'água identifica o leitor e desestimula a cópia não autorizada.",
    "O tema sépia deixa o papel mais quente e cansa menos a vista em leituras longas.",
    "No modo escuro, o fundo da página fica escuro e o texto claro, ideal para ler à noite.",
    "Em tablets na horizontal, o leitor pode mostrar duas páginas lado a lado, como um livro aberto.",
    "Toque nas bordas da tela para avançar ou voltar uma página.",
    "Use dois dedos para ampliar um detalhe da figura e um toque duplo para voltar ao tamanho normal.",
    "O sumário lista os capítulos e as seções que vêm dentro do próprio arquivo PDF.",
    "Cada pedido feito na loja pode ser acompanhado pelo aplicativo, do pagamento até a entrega.",
    "Revisão cuidadosa, normalização e diagramação fazem parte do trabalho editorial.",
    "O registro DOI dá a cada livro e capítulo um endereço permanente para citação.",
    "Este texto não tem valor de conteúdo: serve apenas para demonstrar o funcionamento do aplicativo.",
    "A biblioteca do site e a estante do aplicativo mostram os mesmos livros comprados.",
    "Ao sair da conta, os livros baixados são apagados do aparelho por segurança.",
    "Pesquise palavras como conexão, marcador, ultrassonografia ou estante para ver os resultados.",
]

CAPITULOS = [
    ("Por que ler no aplicativo", ["Leitura em qualquer lugar", "A estante sempre atualizada"]),
    ("Navegando pelo livro", ["Virar páginas e ampliar", "Sumário e busca"]),
    ("Marcadores e progresso", ["Guardando trechos importantes", "Continuar de onde parou"]),
    ("Leitura sem internet", ["Baixar para ler offline", "Espaço usado no aparelho"]),
    ("Conforto visual", ["Temas claro, sépia e escuro", "Uma ou duas páginas"]),
    ("Pedidos e entregas", ["Situação do pedido", "Código de rastreio"]),
]


def esc(texto: str) -> bytes:
    out = bytearray()
    for b in texto.encode("cp1252"):
        if b in (0x28, 0x29, 0x5C):
            out += b"\\" + bytes([b])
        elif b < 32 or b > 126:
            out += b"\\%03o" % b
        else:
            out.append(b)
    return b"(" + bytes(out) + b")"


def utf16(texto: str) -> bytes:
    return b"<FEFF" + texto.encode("utf-16-be").hex().upper().encode() + b">"


def quebrar(texto: str, largura: int):
    linhas, atual = [], ""
    for palavra in texto.split():
        if len(atual) + len(palavra) + 1 > largura:
            linhas.append(atual)
            atual = palavra
        else:
            atual = (atual + " " + palavra).strip()
    if atual:
        linhas.append(atual)
    return linhas


class Pagina:
    def __init__(self):
        self.ops = []

    def texto(self, x, y, s, fonte="F1", tam=10.5, cor=(0.12, 0.16, 0.2)):
        self.ops.append(b"%.3f %.3f %.3f rg BT /%s %.1f Tf %.1f %.1f Td %s Tj ET" % (cor[0], cor[1], cor[2], fonte.encode(), tam, x, y, esc(s)))

    def ret(self, x, y, w, h, cor):
        self.ops.append(b"%.3f %.3f %.3f rg %.1f %.1f %.1f %.1f re f" % (cor[0], cor[1], cor[2], x, y, w, h))

    def circ(self, cx, cy, r, cor):
        k = 0.5523 * r
        self.ops.append(b"%.3f %.3f %.3f rg %.1f %.1f m %.1f %.1f %.1f %.1f %.1f %.1f c %.1f %.1f %.1f %.1f %.1f %.1f c %.1f %.1f %.1f %.1f %.1f %.1f c %.1f %.1f %.1f %.1f %.1f %.1f c f" % (
            cor[0], cor[1], cor[2], cx + r, cy,
            cx + r, cy + k, cx + k, cy + r, cx, cy + r,
            cx - k, cy + r, cx - r, cy + k, cx - r, cy,
            cx - r, cy - k, cx - k, cy - r, cx, cy - r,
            cx + k, cy - r, cx + r, cy - k, cx + r, cy))

    def conteudo(self):
        return b"\n".join(self.ops)


AZUL = (0, 0.588, 0.839)
AZUL_ESC = (0.043, 0.31, 0.541)
CINZA = (0.45, 0.5, 0.55)


def cabecalho(p, n):
    p.texto(MARGEM, A - 28, "Guia do Leitor Digital · Conexão Editora" if VITRINE else "AMOSTRA · dados de exemplo do app Conexão Editora", "F1", 7.5, CINZA)
    p.texto(L / 2 - 6, 24, str(n), "F1", 8.5, CINZA)


def main():
    random.seed(42)
    frases = [f for i, f in enumerate(FRASES) if not (VITRINE and i in (18, 21))]
    paginas = []
    outline = []  # [(titulo, indice_pagina, [(sub, indice)])]

    capa = Pagina()
    capa.ret(0, 0, L, A, AZUL)
    for i in range(8):
        ang = math.pi * 2 * i / 8
        capa.circ(L / 2 + 70 * math.cos(ang), 420 + 70 * math.sin(ang), 13, (1, 1, 1))
    capa.circ(L / 2, 420, 34, (1, 1, 1))
    capa.texto(MARGEM, 290, "Guia do Leitor Digital", "F2", 26, (1, 1, 1))
    capa.texto(MARGEM, 262, "Livro de demonstração do aplicativo", "F1", 13, (1, 1, 1))
    capa.texto(MARGEM, 120, "Equipe Conexão Editora" if VITRINE else "Equipe Conexão Editora (exemplo)", "F2", 11, (1, 1, 1))
    if not VITRINE:
        capa.texto(MARGEM, 60, "AMOSTRA · conteúdo fictício para testes", "F1", 9, (0.9, 0.96, 1))
    paginas.append(capa)

    aviso = Pagina()
    cabecalho(aviso, 2)
    aviso.texto(MARGEM, A - 90, "Apresentação" if VITRINE else "Sobre esta amostra", "F2", 18, AZUL_ESC)
    y = A - 125
    texto_aviso = (
        "Este guia mostra como aproveitar a leitura digital no aplicativo da Conexão Editora: virar páginas, ampliar figuras, "
        "marcar trechos, buscar palavras e ler sem internet. " + FRASES[0] + " " + FRASES[6]
        if VITRINE
        else "Este arquivo foi gerado automaticamente para testar o leitor do aplicativo da Conexão Editora no modo de demonstração. Nenhum texto aqui é de um livro real. " + FRASES[18] + " " + FRASES[21]
    )
    for linha in quebrar(texto_aviso, 62):
        aviso.texto(MARGEM, y, linha)
        y -= 15
    paginas.append(aviso)

    sumario = Pagina()
    cabecalho(sumario, 3)
    sumario.texto(MARGEM, A - 90, "Sumário", "F2", 18, AZUL_ESC)
    paginas.append(sumario)
    linhas_sumario = []

    for c, (titulo, secoes) in enumerate(CAPITULOS, start=1):
        inicio = len(paginas)
        subs = []
        for s_i, sec in enumerate(secoes):
            for parte in range(3 if s_i == 0 else 2):
                p = Pagina()
                cabecalho(p, len(paginas) + 1)
                y = A - 70
                if s_i == 0 and parte == 0:
                    p.ret(MARGEM, y - 6, 40, 4, AZUL)
                    p.texto(MARGEM, y - 30, "Capítulo %d" % c, "F1", 11, AZUL)
                    p.texto(MARGEM, y - 56, titulo, "F2", 19, AZUL_ESC)
                    y -= 90
                if parte == 0:
                    subs.append((f"{c}.{s_i + 1} {sec}", len(paginas)))
                    p.texto(MARGEM, y, f"{c}.{s_i + 1} {sec}", "F2", 13, (0.12, 0.16, 0.2))
                    y -= 24
                if parte == 1 and c % 2 == 1:
                    p.ret(MARGEM, y - 120, L - 2 * MARGEM, 120, (0.92, 0.96, 0.99))
                    for b in range(5):
                        alt = 20 + ((b * 37 + c * 13) % 80)
                        p.ret(MARGEM + 20 + b * 60, y - 110, 36, alt, AZUL if b % 2 == 0 else AZUL_ESC)
                    p.texto(MARGEM, y - 136, f"Figura {c}.{parte}: gráfico ilustrativo (dados fictícios).", "F3", 9, CINZA)
                    y -= 160
                while y > 70:
                    paragrafo = " ".join(random.sample(frases, 3))
                    for linha in quebrar(paragrafo, 62):
                        if y <= 60:
                            break
                        p.texto(MARGEM, y, linha)
                        y -= 15
                    y -= 8
                paginas.append(p)
        outline.append((f"Capítulo {c}: {titulo}", inicio, subs))
        linhas_sumario.append((f"{c}. {titulo}", inicio + 1))

    fim = Pagina()
    cabecalho(fim, len(paginas) + 1)
    fim.texto(MARGEM, A / 2, "Fim" if VITRINE else "Fim da amostra", "F2", 18, AZUL_ESC)
    fim.texto(MARGEM, A / 2 - 24, "Obrigado pela leitura." if VITRINE else "Obrigado por testar o leitor da Conexão Editora.", "F1", 11)
    paginas.append(fim)
    outline.append(("Fim" if VITRINE else "Fim da amostra", len(paginas) - 1, []))

    y = A - 125
    for t, n in linhas_sumario:
        sumario.texto(MARGEM, y, t, "F1", 11)
        sumario.texto(L - MARGEM - 14, y, str(n), "F1", 11, CINZA)
        y -= 22

    # --- monta o PDF ---
    objs = {}
    n_pag = len(paginas)
    prox = 7  # 1 catálogo, 2 páginas, 3 outlines, 4-6 fontes
    ids_pagina = []
    for _ in paginas:
        ids_pagina.append((prox, prox + 1))
        prox += 2
    objs[4] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>"
    objs[5] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>"
    objs[6] = b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>"
    for p, (pid, cid) in zip(paginas, ids_pagina):
        dados = p.conteudo()
        objs[cid] = b"<< /Length %d >>\nstream\n" % len(dados) + dados + b"\nendstream"
        objs[pid] = b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> >> /Contents %d 0 R >>" % (L, A, cid)
    objs[2] = b"<< /Type /Pages /Kids [" + b" ".join(b"%d 0 R" % pid for pid, _ in ids_pagina) + b"] /Count %d >>" % n_pag

    itens = []
    for titulo, idx, subs in outline:
        oid = prox
        prox += 1
        filhos = []
        for st, si in subs:
            filhos.append((prox, st, si))
            prox += 1
        itens.append((oid, titulo, idx, filhos))

    def dest(i):
        return b"[%d 0 R /XYZ null null null]" % ids_pagina[i][0]

    for k, (oid, titulo, idx, filhos) in enumerate(itens):
        d = b"<< /Title " + utf16(titulo) + b" /Parent 3 0 R /Dest " + dest(idx)
        if k > 0:
            d += b" /Prev %d 0 R" % itens[k - 1][0]
        if k < len(itens) - 1:
            d += b" /Next %d 0 R" % itens[k + 1][0]
        if filhos:
            d += b" /First %d 0 R /Last %d 0 R /Count %d" % (filhos[0][0], filhos[-1][0], len(filhos))
        objs[oid] = d + b" >>"
        for j, (fid, st, si) in enumerate(filhos):
            f = b"<< /Title " + utf16(st) + b" /Parent %d 0 R /Dest " % oid + dest(si)
            if j > 0:
                f += b" /Prev %d 0 R" % filhos[j - 1][0]
            if j < len(filhos) - 1:
                f += b" /Next %d 0 R" % filhos[j + 1][0]
            objs[fid] = f + b" >>"
    total_outline = len(itens) + sum(len(i[3]) for i in itens)
    objs[3] = b"<< /Type /Outlines /First %d 0 R /Last %d 0 R /Count %d >>" % (itens[0][0], itens[-1][0], total_outline)
    objs[1] = b"<< /Type /Catalog /Pages 2 0 R /Outlines 3 0 R /PageMode /UseOutlines /Lang (pt-BR) >>"

    saida = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = {}
    for i in range(1, prox):
        offsets[i] = len(saida)
        saida += b"%d 0 obj\n" % i + objs[i] + b"\nendobj\n"
    xref = len(saida)
    saida += b"xref\n0 %d\n0000000000 65535 f \n" % prox
    for i in range(1, prox):
        saida += b"%010d 00000 n \n" % offsets[i]
    saida += b"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n" % (prox, xref)
    SAIDA.parent.mkdir(parents=True, exist_ok=True)
    SAIDA.write_bytes(bytes(saida))
    print(f"{SAIDA} - {n_pag} paginas - {len(saida)} bytes")


if __name__ == "__main__":
    main()
