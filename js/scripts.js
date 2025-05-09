document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.getElementById('menuBtn');
    const kouList = document.getElementById('kouList');
    const closeMenu = document.getElementById('closeMenu');
    
    // メニューの表示・非表示
    if(menuBtn && kouList && closeMenu) {
        menuBtn.addEventListener('click', function() {
            kouList.classList.remove('hidden');
        });
        closeMenu.addEventListener('click', function() {
            kouList.classList.add('hidden');
        });
    }
    
    // タブ切り替え機能
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if(tabButtons.length > 0 && tabContents.length > 0) {
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                // すべてのタブからアクティブクラスを削除
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // クリックされたタブをアクティブに
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    }
});
