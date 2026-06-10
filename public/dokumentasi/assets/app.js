// Pencarian klien + menu HP — Panduan pesantren.online
(function () {
  'use strict';
  var input = document.getElementById('searchInput');
  var box = document.getElementById('searchResults');
  var toggle = document.getElementById('navToggle');

  // prefix relatif ke root situs (halaman panduan ada 1 tingkat di bawah root)
  var ROOT = document.querySelector('.layout') ? '../' : '';

  if (toggle) {
    toggle.addEventListener('click', function () {
      document.body.classList.toggle('nav-open');
    });
    document.addEventListener('click', function (e) {
      if (document.body.classList.contains('nav-open') &&
          !e.target.closest('.sidebar') && !e.target.closest('#navToggle')) {
        document.body.classList.remove('nav-open');
      }
    });
  }

  if (!input || !window.SEARCH_INDEX) return;
  var INDEX = window.SEARCH_INDEX;
  var sel = -1;

  function norm(s) { return (s || '').toLowerCase(); }

  function score(item, words) {
    var t = norm(item.t), h = norm(item.h), s = norm(item.s), total = 0;
    for (var i = 0; i < words.length; i++) {
      var w = words[i], pts = 0;
      if (t.indexOf(w) >= 0) pts = 5;
      else if (h.indexOf(w) >= 0) pts = 3;
      else if (s.indexOf(w) >= 0) pts = 1;
      if (!pts) return 0; // semua kata harus cocok
      total += pts;
    }
    return total;
  }

  function render(results, q) {
    if (!q) { box.hidden = true; box.innerHTML = ''; return; }
    if (!results.length) {
      box.innerHTML = '<div class="none">Tidak ditemukan. Coba kata lain, contoh: <b>tagihan</b>, <b>nilai</b>, <b>masuk</b>.</div>';
      box.hidden = false; return;
    }
    box.innerHTML = results.map(function (r) {
      var label = r.h ? r.t + ' <small>›</small> ' + r.h : r.t;
      return '<a href="' + ROOT + r.u + '"><span class="r-title">' + label + '</span>' +
             '<span class="r-snip">' + (r.s || '') + '</span></a>';
    }).join('');
    box.hidden = false; sel = -1;
  }

  input.addEventListener('input', function () {
    var q = norm(input.value).trim();
    if (q.length < 2) { render([], ''); return; }
    var words = q.split(/\s+/);
    var results = INDEX
      .map(function (it) { return { it: it, sc: score(it, words) }; })
      .filter(function (x) { return x.sc > 0; })
      .sort(function (a, b) { return b.sc - a.sc; })
      .slice(0, 8)
      .map(function (x) { return x.it; });
    render(results, q);
  });

  input.addEventListener('keydown', function (e) {
    var links = box.querySelectorAll('a');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!links.length) return;
      sel = e.key === 'ArrowDown' ? Math.min(sel + 1, links.length - 1) : Math.max(sel - 1, 0);
      links.forEach(function (l, i) { l.classList.toggle('sel', i === sel); });
      links[sel].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && sel >= 0 && links[sel]) {
      window.location.href = links[sel].href;
    } else if (e.key === 'Escape') {
      render([], ''); input.blur();
    }
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#search')) { box.hidden = true; }
  });

  // tekan "/" di mana pun = langsung fokus ke pencarian
  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && document.activeElement !== input &&
        !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) {
      e.preventDefault(); input.focus();
    }
  });
})();
