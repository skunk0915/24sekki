import csv, re, time, requests, os, urllib.parse
from bs4 import BeautifulSoup

BASE_DIR = "https://www.kurashi-no-hotorisya.jp/blog/4seasons-things/24seasonal-terms/"
INDEX = urllib.parse.urljoin(BASE_DIR, "24seasonal-terms_all.html")
HEADERS = {"User-Agent": "Mozilla/5.0"}

def get_links() -> list[str]:
    """一覧ページから個別記事へのリンクを取得"""
    r = requests.get(INDEX, headers=HEADERS, timeout=15)
    r.raise_for_status()
    soup = BeautifulSoup(r.content, "lxml")
    links = []
    for a in soup.select("a[href$='.html']"):
        href = urllib.parse.urljoin(INDEX, a["href"])
        if "/24seasonal-terms/" in href and not href.endswith("24seasonal-terms_all.html"):
            links.append(href)
    # 重複排除 & ソート（slug 昇順）
    links = sorted(set(links), key=lambda u: os.path.basename(u))
    return links

def scrape_one(url: str):
    r = requests.get(url, headers=HEADERS, timeout=15)
    r.raise_for_status()
    r.encoding = "utf-8"
    soup = BeautifulSoup(r.text, "lxml")

    og = soup.find("meta", property="og:image")
    img = og["content"] if og else ""

    h1 = soup.h1.get_text(" ", strip=True) if soup.h1 else ""

    # 二十四節気「立春 (りっしゅん)」2/4～2/18頃
    name = yomi = start = end = ""
    m = re.match(
        r'二十四節気[「"](.+?)\s*\((.+?)\)[」"]\s*(\d{1,2}/\d{1,2})～(\d{1,2}/\d{1,2})',
        h1,
    )
    if m:
        name, yomi, start, end = m.groups()

    # 本文：h1 直後の <p> 2つ
    body_paras = [
        p.get_text(strip=True)
        for p in soup.h1.find_all_next("p", limit=2)
    ]
    body = "\n\n".join(body_paras)

    slug = os.path.splitext(os.path.basename(url))[0]
    return [name, yomi, start, end, body, img, h1, slug]

def main():
    links = get_links()
    with open("24sekki.csv", "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.writer(f)
        writer.writerow(["節気名", "読み", "開始年月日", "終了年月日", "本文", "画像URL", "raw_title", "slug"])
        for url in links:
            try:
                row = scrape_one(url)
                writer.writerow(row)
                print("OK", row[0])
                time.sleep(0.5)
            except Exception as e:
                print("NG", url, e)

if __name__ == "__main__":
    main()
