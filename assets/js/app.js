(() => {
  'use strict';
  const doc = document.documentElement;
  const saved = localStorage.getItem('ems-theme');
  if (saved === 'dark') doc.dataset.theme = 'dark';
  document.getElementById('themeToggle')?.addEventListener('click', () => {
    doc.dataset.theme = doc.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('ems-theme', doc.dataset.theme);
  });
  const side = document.getElementById('sidebar'), shade = document.getElementById('mobileShade');
  const toggle = () => { side?.classList.toggle('open'); shade?.classList.toggle('open'); };
  document.getElementById('menuToggle')?.addEventListener('click', toggle);
  shade?.addEventListener('click', toggle);
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', ev => {if (!confirm(form.dataset.confirm || 'Confirmer cette action ?')) ev.preventDefault(); });
  });
  const search = document.querySelector('[data-table-search]');
  search?.addEventListener('input', () => {
    const term = search.value.toLocaleLowerCase().trim();
    document.querySelectorAll('[data-search-row]').forEach(row => {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(term);
    });
  });
  const avail = document.getElementById('checkAvailability');
  const result = document.getElementById('availabilityResult');
  avail?.addEventListener('click', async () => {
    const form = avail.closest('form'); const data = new FormData(form);
    const qs = new URLSearchParams();
    ['booking_date','start_time','end_time','attendees'].forEach(k => qs.set(k, data.get(k) || ''));
    avail.disabled = true; if (result) result.textContent = 'Recherche en cours…';
    try {
      const res = await fetch(window.EMS.base + 'api_disponibilite.php?' + qs, {headers: {'Accept':'application/json'}});
      const json = await res.json();
      if(!res.ok) throw Error(json.error || 'Erreur');
      if(result) result.innerHTML = '';
      const list = document.createElement('div'); list.className = 'd-flex flex-wrap gap-2 mt-2';
      if(!json.rooms.length) list.textContent = 'Aucune salle disponible sur cette plage horaire.';
      for(const room of json.rooms) {
        const el = document.createElement('button'); el.type='button'; el.className='btn btn-outline-primary btn-sm';
        el.textContent = room.name + ' (' + room.capacity + ' places)';
        el.addEventListener('click', () => { form.querySelector('[name=room_id]').value = room.id; });
        list.appendChild(el);
      }
      result?.appendChild(list);
    } catch(err) { if(result) result.textContent = err.message; }
    finally {avail.disabled = false;}
  });
})();
