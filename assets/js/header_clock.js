/**
 * Header live clock (12-hour) + weekday — updates every second
 */
(function () {
    var clockEl = document.getElementById('headerClock');
    var dayEl = document.getElementById('headerDay');
    if (!clockEl && !dayEl) return;

    var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function format12(now) {
        var h = now.getHours();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return pad(h) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm;
    }

    function tick() {
        var now = new Date();
        if (clockEl) {
            clockEl.textContent = format12(now);
        }
        if (dayEl) {
            dayEl.textContent = days[now.getDay()];
        }
    }

    tick();
    setInterval(tick, 1000);
})();
