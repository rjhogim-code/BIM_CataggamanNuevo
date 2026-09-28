(() => {
  'use strict';
const keys = { residents: 'cataggaman_residents', incidents: 'cataggaman_incidents', certificates: 'cataggaman_certificates' };
const userKey = 'cataggaman_users';
const read = key => { try { return JSON.parse(localStorage.getItem(key) || '[]');
} catch { return [];
} };
const save = (key, value) => localStorage.setItem(key, JSON.stringify(value));
const id = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
const residents = () => read(keys.residents);
const incidents = () => read(keys.incidents);
const page = document.body.dataset.page;
if (page !== 'login' && sessionStorage.getItem('cataggaman_logged_in') !== 'true') {
  window.location.replace('login.html');
  return;
}
const currentUser = () => localStorage.getItem('cataggaman_current_user') || 'Unknown user';
function setupLogin() {
  const form = document.querySelector('#loginForm');
  if (!form) return;
  const users = () => read(userKey);
  const showMessage = (id, text) => { const message = document.querySelector(`#${id}`); message.textContent = text; message.hidden = false; };
  const loginCard = document.querySelector('#loginCard');
  const registerCard = document.querySelector('#registerCard');
  document.querySelector('#showRegister').onclick = () => { loginCard.hidden = true; registerCard.hidden = false; };
  document.querySelector('#showLogin').onclick = () => { registerCard.hidden = true; loginCard.hidden = false; };
  document.querySelector('#registerRole').onchange = event => { document.querySelector('#customRoleField').hidden = event.target.value !== 'Other'; };
  form.onsubmit = event => {
    event.preventDefault();
    const username = document.querySelector('#username').value.trim();
    const password = document.querySelector('#password').value.trim();
    const account = users().find(item => item.username.toLowerCase() === username.toLowerCase());
    if (!account || account.password !== password) { showMessage('loginMessage', 'Username or password is incorrect. Please register or try again.'); return; }
    sessionStorage.setItem('cataggaman_logged_in', 'true');
    localStorage.setItem('cataggaman_current_user', account.role);
    window.location.href = 'index.html';
  };
  document.querySelector('#registerForm').onsubmit = event => {
    event.preventDefault();
    const username = document.querySelector('#registerUsername').value.trim();
    const password = document.querySelector('#registerPassword').value;
    const roleChoice = document.querySelector('#registerRole').value;
    const customRole = document.querySelector('#customRole').value.trim();
    if (users().some(item => item.username.toLowerCase() === username.toLowerCase())) { showMessage('registerMessage', 'That username is already registered.'); return; }
    if (roleChoice === 'Other' && !customRole) { showMessage('registerMessage', 'Please specify your user identifier.'); return; }
    const role = roleChoice === 'Other' ? customRole : roleChoice;
    save(userKey, [...users(), { id:id(), username, password, role, createdAt:new Date().toISOString() }]);
    document.querySelector('#registerForm').reset();
    document.querySelector('#customRoleField').hidden = true;
    registerCard.hidden = true; loginCard.hidden = false;
    showMessage('loginMessage', 'Account registered. Sign in with your new credentials.');
  };
}
function setupUser() {
  const selector = document.querySelector('#currentUser');
  if (selector) {
    selector.textContent = currentUser();
    const updateRecordedBy = value => document.querySelectorAll('[data-recorded-by]').forEach(field => { field.value = value; });
    updateRecordedBy(currentUser());
  }
  const logout = document.querySelector('#logout');
  if (logout) logout.onclick = () => { localStorage.removeItem('cataggaman_current_user'); sessionStorage.removeItem('cataggaman_logged_in'); window.location.href = 'login.html'; };
}
function setupNavigation() {
    document.querySelectorAll('.nav').forEach(link => link.classList.toggle('active', link.dataset.page === page));
const menu = document.querySelector('#menu');
if (menu) menu.onclick = () => document.querySelector('#sidebar').classList.toggle('open');
const clock = document.querySelector('#clock');
if (clock) { const update = () => { clock.textContent = new Intl.DateTimeFormat(undefined, { weekday:'short', month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' }).format(new Date());
};
update();
setInterval(update, 30000);
}
  }
  function renderDashboard() {
    const residentCount = document.querySelector('#residentCount');
if (!residentCount) return;
const allResidents = residents(), allIncidents = incidents(), allCertificates = read(keys.certificates), today = new Date().toISOString().slice(0, 10);
residentCount.textContent = allResidents.length;
document.querySelector('#incidentCount').textContent = allIncidents.length;
document.querySelector('#activeCases').textContent = `${allIncidents.filter(item => item.status !== 'Resolved').length} active cases`;
document.querySelector('#certificateCount').textContent = allCertificates.filter(item => item.date === today).length;
const activity = [...allResidents.map(item => ({ text:`Resident record added: ${item.name}`, date:item.createdAt })), ...allIncidents.map(item => ({ text:`Incident reported: ${item.caseNo} Â· ${item.type}`, date:item.createdAt })), ...allCertificates.map(item => ({ text:`${item.type} issued to ${item.name}`, date:item.createdAt }))].sort((a,b) => new Date(b.date) - new Date(a.date)).slice(0, 6);
document.querySelector('#activity').innerHTML = activity.map(item => `<div class="activity">${escapeHtml(item.text)}<small>${new Date(item.date).toLocaleString()}</small></div>`).join('') || '<div class="empty">No activity recorded yet.</div>';
}
  function renderResidents() {
    const rows = document.querySelector('#residentRows');
if (!rows) return;
const query = document.querySelector('#residentSearch').value.toLowerCase(), list = residents().filter(item => Object.values(item).some(value => String(value).toLowerCase().includes(query)));
rows.innerHTML = list.map(item => `<tr><td><b>${escapeHtml(item.name)}</b></td><td>${escapeHtml(item.age || 'â€”')}</td><td>${escapeHtml(item.gender || 'â€”')}</td><td>${escapeHtml(item.civil || 'â€”')}</td><td>${escapeHtml(item.zone || 'â€”')}</td><td>${escapeHtml(item.contact || 'â€”')}</td><td>${escapeHtml(item.voter || 'â€”')}</td><td><button class="btn" data-edit-resident="${escapeHtml(item.id)}">Edit</button> <button class="btn danger" data-delete-resident="${escapeHtml(item.id)}">Delete</button></td></tr>`).join('') || '<tr><td class="empty" colspan="8">No residents found.</td></tr>';
}
  function setupResidentForm() {
    const dialog = document.querySelector('#recordDialog'), form = document.querySelector('#residentForm');
if (!form) return;
let editId = null;
const open = item => { editId = item?.id || null;
form.reset();
document.querySelector('[data-recorded-by]').value = currentUser();
document.querySelector('#residentForm h3').textContent = editId ? 'Edit resident' : 'Add resident';
if (item) Object.entries(item).forEach(([name, value]) => { if (form.elements[name]) form.elements[name].value = value;
});
dialog.showModal();
};
document.querySelector('#newResident').onclick = () => open();
document.querySelector('#newResident').addEventListener('edit', event => open(event.detail));
document.querySelector('#closeDialog').onclick = document.querySelector('#cancelDialog').onclick = () => dialog.close();
form.onsubmit = event => { event.preventDefault();
const list = residents(), data = Object.fromEntries(new FormData(form));
if (editId) { const index = list.findIndex(item => item.id === editId);
if (index >= 0) list[index] = { ...list[index], ...data };
} else list.unshift({ id:id(), ...data, recordedBy:currentUser(), createdAt:new Date().toISOString() });
save(keys.residents, list);
dialog.close();
renderResidents();
renderDashboard();
};
['residentSearch'].forEach(field => document.querySelector(`#${field}`).addEventListener('input', renderResidents));
}
  function renderIncidents() {
    const rows = document.querySelector('#incidentRows');
if (!rows) return;
const query = document.querySelector('#incidentSearch').value.toLowerCase(), status = document.querySelector('#incidentFilter').value;
const list = incidents().filter(item => (!status || item.status === status) && Object.values(item).some(value => String(value).toLowerCase().includes(query)));
rows.innerHTML = list.map(item => `<tr><td><b>${escapeHtml(item.caseNo)}</b></td><td>${escapeHtml(item.complainant)}</td><td>${escapeHtml(item.respondent || 'â€”')}</td><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.date ? new Date(item.date).toLocaleString() : 'â€”')}</td><td><span class="badge ${item.status === 'Resolved' ? 'resolved' : item.status === 'Under Investigation' ? 'investigation' : ''}">${escapeHtml(item.status)}</span></td><td><button class="btn" data-cycle-incident="${escapeHtml(item.id)}">Update status</button> <button class="btn danger" data-delete-incident="${escapeHtml(item.id)}">Delete</button></td></tr>`).join('') || '<tr><td class="empty" colspan="7">No incident reports found.</td></tr>';
}
  function setupIncidentForm() {
    const dialog = document.querySelector('#recordDialog'), form = document.querySelector('#incidentForm');
if (!form) return;
let editId = null;
const open = item => { editId = item?.id || null;
form.reset();
document.querySelector('[data-recorded-by]').value = currentUser();
if (item) Object.entries(item).forEach(([name, value]) => { if (form.elements[name]) form.elements[name].value = value;
});
else { form.elements.caseNo.value = `CN-${new Date().getFullYear()}-${String(incidents().length + 1).padStart(3, '0')}`;
form.elements.date.value = new Date().toISOString().slice(0, 16);
} dialog.showModal();
};
document.querySelector('#newIncident').onclick = () => open();
document.querySelector('#closeDialog').onclick = document.querySelector('#cancelDialog').onclick = () => dialog.close();
form.onsubmit = event => { event.preventDefault();
const list = incidents(), data = Object.fromEntries(new FormData(form));
if (editId) { const index = list.findIndex(item => item.id === editId);
if (index >= 0) list[index] = { ...list[index], ...data };
} else list.unshift({ id:id(), ...data, recordedBy:currentUser(), createdAt:new Date().toISOString() });
save(keys.incidents, list);
dialog.close();
renderIncidents();
renderDashboard();
};
['incidentSearch','incidentFilter'].forEach(field => document.querySelector(`#${field}`).addEventListener('input', renderIncidents));
}
  function setupCertificates() {
    const residentSelect = document.querySelector('#certResident');
if (!residentSelect) return;
residentSelect.innerHTML = '<option value="">Manual entry</option>' + residents().map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.name)}</option>`).join('');
const update = () => { const type = document.querySelector('#certType').value, date = document.querySelector('#certDate').value;
document.querySelector('#previewType').textContent = type.toUpperCase();
document.querySelector('#previewName').textContent = document.querySelector('#certName').value || '[RESIDENT NAME]';
document.querySelector('#previewAddress').textContent = document.querySelector('#certAddress').value || '[ZONE / SITIO]';
document.querySelector('#previewPurpose').textContent = document.querySelector('#certPurpose').value || '[PURPOSE]';
document.querySelector('#previewDate').textContent = date ? new Date(`${date}T12:00:00`).toLocaleDateString(undefined, { year:'numeric', month:'long', day:'numeric' }) : '';
};
residentSelect.onchange = event => { const item = residents().find(resident => resident.id === event.target.value);
if (item) { document.querySelector('#certName').value = item.name;
document.querySelector('#certAddress').value = item.zone || '';
} update();
};
document.querySelector('#certDate').value = new Date().toISOString().slice(0, 10);
['certType','certName','certAddress','certPurpose','certDate'].forEach(field => document.querySelector(`#${field}`).addEventListener('input', update));
document.querySelector('#printCert').onclick = () => { if (!document.querySelector('#certName').value.trim()) return alert('Select or enter a resident first.');
const list = read(keys.certificates);
list.unshift({ id:id(), name:document.querySelector('#certName').value.trim(), type:document.querySelector('#certType').value, date:document.querySelector('#certDate').value, recordedBy:currentUser(), createdAt:new Date().toISOString() });
save(keys.certificates, list);
renderDashboard();
window.print();
};
update();
}
  document.addEventListener('click', event => { const button = event.target.closest('button');
if (!button) return;
if (button.dataset.deleteResident && confirm('Delete this resident record?')) { save(keys.residents, residents().filter(item => item.id !== button.dataset.deleteResident));
renderResidents();
renderDashboard();
} if (button.dataset.editResident) { const item = residents().find(resident => resident.id === button.dataset.editResident);
if (item) document.querySelector('#newResident').dispatchEvent(new CustomEvent('edit', { detail:item }));
} if (button.dataset.deleteIncident && confirm('Delete this incident report?')) { save(keys.incidents, incidents().filter(item => item.id !== button.dataset.deleteIncident));
renderIncidents();
renderDashboard();
} if (button.dataset.cycleIncident) { const list = incidents(), item = list.find(incident => incident.id === button.dataset.cycleIncident);
if (item) { item.status = ({ Pending:'Under Investigation', 'Under Investigation':'Resolved', Resolved:'Pending' })[item.status] || 'Pending';
save(keys.incidents, list);
renderIncidents();
renderDashboard();
} } });
if (page === 'login') {
  setupLogin();
} else {
  setupNavigation();
  setupUser();
  renderDashboard();
  renderResidents();
  renderIncidents();
  setupResidentForm();
  setupIncidentForm();
  setupCertificates();
}
})();
