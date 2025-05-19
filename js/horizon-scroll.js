// 縦書きで読み進めるときの横スクロールをマウスホイールで可能に
window.addEventListener('wheel', function(e) {
    if (e.shiftKey) return;
  
    const scrollTarget = document.querySelector('body > .content > .vertical-text');
  
    if (scrollTarget && e.deltaY !== 0) {
      e.preventDefault();
      const speed = 3;
      const delta = -e.deltaY * speed;
  
      const threshold = 100; // スムーズにする最大距離（調整可）
  
      scrollTarget.scrollBy({
        left: delta,
        behavior: Math.abs(delta) < threshold ? 'smooth' : 'auto'
      });
    }
  }, { passive: false });
  