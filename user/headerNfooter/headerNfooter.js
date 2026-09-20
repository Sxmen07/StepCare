async function loadPartial(id,url) {
    const el = document.getElementById(id);
    if(!el) return;
    const res = await fetch(url);
    el.innerHTML = await res.text();
}

document.addEventListener("DOMContentLoaded", ()=>{
    loadPartial("header","../headerNfooter/header/header.html");
    loadPartial("footer","../headerNfooter/footer/footer.html");
});