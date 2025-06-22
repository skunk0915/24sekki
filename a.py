import csv, re, time
import requests
from bs4 import BeautifulSoup
import sys
import io

# コンソール出力のエンコーディングをUTF-8に設定
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

BASE = "https://www.kurashi-no-hotorisya.jp/blog/4seasons-things/72seasonal-signs/"
HEADERS = {"User-Agent": "Mozilla/5.0"}

def scrape_one(idx: int) -> tuple[str, str, str, str, str, str, str, str]:  # kou, wamei, yomigana, start_date, end_date, body, img, raw_title
    url = f"{BASE}sign{idx:02d}.html"
    r = requests.get(url, headers=HEADERS, timeout=15)
    r.raise_for_status()
    
    # エンコーディングを明示的に指定
    r.encoding = 'utf-8'
    
    # デバッグ用：エンコーディング情報を表示
    print(f"URL: {url}")
    print(f"Apparent encoding: {r.apparent_encoding}")
    print(f"Used encoding: {r.encoding}")
    
    # レスポンスの内容をバイナリで取得し、UTF-8でデコード
    content = r.content.decode('utf-8', errors='replace')
    soup = BeautifulSoup(content, "lxml")

    # 画像（必ず head 内）
    og = soup.find("meta", property="og:image")
    img = og["content"] if og else ""

    # タイトルと本文
    if not soup.h1:
        print("[デバッグ] soup.h1が見つかりません！")
        return "", "", "", "", "", "", "", ""
    title = soup.h1.get_text(strip=True)

    # title例: 第一候「東風解凍 (はるかぜこおりをとく)」2/4～2/8頃
    kou = ''  # 候名
    wamei = ''  # 和名
    yomigana = ''  # 読み
    start_date = ''
    end_date = ''
    m = re.match(r'(第.+?候)[「"](.+?)\s*\((.+?)\)[」"](\d{1,2}/\d{1,2})～(\d{1,2}/\d{1,2})', title)
    if m:
        kou = m.group(1)
        wamei = m.group(2)
        yomigana = m.group(3)
        start_date = m.group(4)
        end_date = m.group(5)
    else:
        kou_m = re.match(r'(第.+?候)', title)
        kou = kou_m.group(1) if kou_m else ''
        wamei_m = re.search(r'「(.+?)[\(（]', title)
        wamei = wamei_m.group(1).strip() if wamei_m else ''
        yomigana_m = re.search(r'\((.+?)\)', title)
        yomigana = yomigana_m.group(1).strip() if yomigana_m else ''
        date_m = re.search(r'(\d{1,2}/\d{1,2})～(\d{1,2}/\d{1,2})', title)
        if date_m:
            start_date = date_m.group(1)
            end_date = date_m.group(2)
    kou = kou.strip()
    wamei = wamei.strip()
    yomigana = yomigana.strip()

    # デバッグ: h1.parentの内容を表示
    print(f"[デバッグ] h1.parent: {str(soup.h1.parent)[:500]}")
    # デバッグ: h1.parent.childrenをリスト化して全て表示
    children_list = list(soup.h1.parent.children)
    print(f"[デバッグ] h1.parent.childrenの数: {len(children_list)}")
    for idx, c in enumerate(children_list):
        print(f"[デバッグ] children[{idx}]: {getattr(c, 'name', None)} → {str(c)[:80]}")
    # デバッグ: h1.next_siblingsをリスト化して全て表示
    siblings_list = list(soup.h1.next_siblings)
    print(f"[デバッグ] h1.next_siblingsの数: {len(siblings_list)}")
    for idx, s in enumerate(siblings_list):
        print(f"[デバッグ] next_siblings[{idx}]: {getattr(s, 'name', None)} → {str(s)[:80]}")

    # デバッグ: soup全体からp, ul, olタグを抽出
    all_p = soup.find_all('p')
    print(f"[デバッグ] soup内pタグの数: {len(all_p)}")
    for idx, p in enumerate(all_p):
        print(f"[デバッグ] p[{idx}]: {p.get_text(strip=True)[:80]}")
    all_ul = soup.find_all('ul')
    print(f"[デバッグ] soup内ulタグの数: {len(all_ul)}")
    for idx, ul in enumerate(all_ul):
        print(f"[デバッグ] ul[{idx}]: {ul.get_text(strip=True)[:80]}")
    all_ol = soup.find_all('ol')
    print(f"[デバッグ] soup内olタグの数: {len(all_ol)}")
    for idx, ol in enumerate(all_ol):
        print(f"[デバッグ] ol[{idx}]: {ol.get_text(strip=True)[:80]}")

    # 本文はsoup.find_all('p')の最初の2つを連結
    all_p = soup.find_all('p')
    if len(all_p) >= 2:
        body = all_p[0].get_text(strip=True) + "\n\n" + all_p[1].get_text(strip=True)
    elif len(all_p) == 1:
        body = all_p[0].get_text(strip=True)
    else:
        body = ""
    print(f"[デバッグ] 抽出body: {body[:100]}")

    return kou, wamei, yomigana, start_date, end_date, body, img, title

def main():
    # BOM付きUTF-8で書き込み
    with open("72kou.csv", "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.writer(f)
        writer.writerow(["候名", "和名", "読み", "開始年月日", "終了年月日", "本文", "画像URL", "raw_title"])
        
        # デバッグ用に最初の数件だけ処理
        for i in range(1,100):  # テスト用に4件だけ処理
            try:
                row = scrape_one(i)
                # デバッグ用：取得したデータを表示
                print(f"\nデータ {i:02d}:")
                print(f"タイトル: {row[0]}")
                print(f"本文の一部: {row[1][:50]}..." if row[1] else "本文なし")
                print(f"画像URL: {row[2]}")
                
                # データをUTF-8に正規化
                normalized_row = [
                    s.encode('utf-8', errors='replace').decode('utf-8', errors='replace') 
                    if isinstance(s, str) else s 
                    for s in row
                ]
                writer.writerow(normalized_row)
                print(f"OK {i:02d}")
                time.sleep(0.5)          # polite crawl
            except Exception as e:
                import traceback
                traceback.print_exc()
                print(f"NG {i:02d}: {e}")
                
    # デバッグ用：ファイルの内容を確認（バイナリモードで読み込み）
    print("\nCSVファイルの内容（最初の数行）:")
    with open("72kou.csv", "rb") as f:
        content = f.read()
        print(f"ファイルの先頭バイト: {content[:20]}")
    
    with open("72kou.csv", "r", encoding="utf-8-sig") as f:
        for i, line in enumerate(f):
            if i < 5:  # 最初の5行だけ表示
                print(f"行 {i+1}: {line.strip()}")
            else:
                break

if __name__ == "__main__":
    main()
