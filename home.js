// auto scroll announcements every 3 seconds
setInterval(() => {
    const list = document.getElementById("announcements-list");
    const first = list.firstElementChild;
    list.appendChild(first.cloneNode(true));
    list.removeChild(first);
}, 3000);
