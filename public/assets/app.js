'use strict';
const $ = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => [...root.querySelectorAll(s)];
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => new Intl.NumberFormat('vi-VN').format(Number(n) || 0) + ' ₫';
const num = n => new Intl.NumberFormat('vi-VN').format(Number(n) || 0);
const dateTime = s => s ? new Intl.DateTimeFormat('vi-VN',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Ho_Chi_Minh'}).format(new Date(s.replace(' ','T')+'+07:00')) : '—';
const hm = s => s?.slice(11,16) || '';
const labels = {pending:'Chờ xác nhận',confirmed:'Đã xác nhận',checked_in:'Đang sử dụng',completed:'Hoàn tất',cancelled:'Đã hủy',available:'Nhận đặt phòng',maintenance:'Bảo trì',inactive:'Ngừng hoạt động'};
const roles = {admin:'Quản trị viên',staff:'Nhân viên',customer:'Khách hàng'};
const badge = s => `<span class="badge ${esc(s)}">${esc(labels[s] || s)}</span>`;
const icons = {rooms:'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',calendar:'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2m4 0h2"/>',gift:'<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8C5 9 5 2 8 3c2 0 4 5 4 5s2-5 4-5c4-1 4 6-4 5z"/>',users:'<circle cx="9" cy="7" r="4"/><path d="M2 21v-3a7 7 0 0114 0v3M17 3a4 4 0 010 8m3 10v-3a7 7 0 00-3-6"/>',user:'<circle cx="12" cy="7" r="4"/><path d="M4 21v-3a8 8 0 0116 0v3"/>',chart:'<path d="M4 3v18h17M9 17v-6m5 6V6m5 11V9"/>',music:'<path d="M9 18V5l11-2v13M9 8l11-2"/><ellipse cx="6" cy="18" rx="3" ry="3"/><ellipse cx="17" cy="16" rx="3" ry="3"/>',settings:'<path d="M12 3l2 3 4-1 1 4 3 3-3 3-1 4-4-1-2 3-2-3-4 1-1-4-3-3 3-3 1-4 4 1z"/><circle cx="12" cy="12" r="3"/>',arrow:'<path d="M5 12h14m-5-5 5 5-5 5"/>',check:'<path d="M5 12l4 4L19 6"/>',clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',headphones:'<path d="M4 14v-3a8 8 0 0116 0v3"/><rect x="3" y="12" width="4" height="8" rx="2"/><rect x="17" y="12" width="4" height="8" rx="2"/>',logout:'<path d="M9 4H4v16h5m4-8h8m-4-4 4 4-4 4"/>',search:'<circle cx="10" cy="10" r="7"/><path d="M15 15l6 6"/>',star:'<path d="M12 3l3 6 6 1-4 5 1 7-6-3-6 3 1-7-4-5 6-1z"/>'};
const icon = n => `<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${icons[n] || icons.music}</svg>`;
const state = {user:null,csrf:'',rooms:[],services:[],settings:{},page:'rooms',search:null};
let renderId=0, lastFocus=null, detailId=null;

async function api(action, data, query={}) {
  const url='/api?'+new URLSearchParams({action,...query});
  const options={credentials:'same-origin',headers:{}};
  if (data !== undefined) { options.method='POST'; options.headers={'Content-Type':'application/json','X-CSRF-Token':state.csrf}; options.body=JSON.stringify(data); }
  const response=await fetch(url, options);
  let json;
  try { json=await response.json(); } catch { throw new Error('Máy chủ chưa sẵn sàng. Vui lòng thử lại.'); }
  if (!response.ok) throw new Error(json.error || 'Không thể xử lý yêu cầu.');
  return json.data;
}
function toast(message, error=false) {
  const el=document.createElement('div'); el.className='toast'+(error?' error-toast':''); el.textContent=message; $('#toast-root').append(el); setTimeout(()=>el.remove(),5500);
}
function dayPlus(date,days) { const d=new Date(date+'T12:00:00+07:00'); d.setDate(d.getDate()+days); return new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).format(d); }
function field(label,name,type='text',value='',extra='') { return `<label class="field"><span>${label}</span><input type="${type}" name="${name}" value="${esc(value)}" ${extra}></label>`; }
function selectField(label,name,options,value='') { return `<label class="field"><span>${label}</span><select name="${name}">${options.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select></label>`; }
function formData(form) { return Object.fromEntries(new FormData(form)); }
function empty(title,text,action='') { return `<div class="empty">${icon('calendar')}<h3>${title}</h3><p>${text}</p>${action}</div>`; }
function pageHead(title,sub,button='') { return `<div class="page-head"><div><h1>${title}</h1><p>${sub}</p></div>${button}</div>`; }
function nav() {
  const staff=state.user && state.user.role!=='customer';
  const links=[['rooms','rooms','Khám phá phòng'],['bookings','calendar',staff?'Quản lý lịch đặt':'Lịch đặt của tôi'],['loyalty','gift','Điểm thưởng'],['profile','user','Tài khoản cá nhân']];
  if(staff) links.push(['dashboard','chart','Tổng quan'],['customers','users','Khách hàng'],['reports','chart','Báo cáo doanh thu']);
  if(state.user?.role==='admin') links.push(['manage','rooms','Quản lý phòng'],['services','music','Dịch vụ bổ sung'],['settings','settings','Cài đặt hệ thống']);
  $('#navigation').innerHTML=links.map(([id,i,t],n)=>`${n===4||n===7?'<div class="nav-divider"></div>':''}<a href="#${id}" class="nav-link ${state.page===id?'active':''}" ${state.page===id?'aria-current="page"':''}>${icon(i)}<span>${t}</span></a>`).join('');
  const u=state.user;
  $('#top-user').innerHTML=u?`<a class="top-profile" href="#profile"><span>${esc(u.name)}</span><b class="avatar">${esc(u.name.slice(0,1).toUpperCase())}</b></a>`:'<button class="btn small secondary" data-click="login">Đăng nhập '+icon('arrow')+'</button>';
  $('#side-user').innerHTML=u?`<div class="user-block"><span class="avatar">${esc(u.name[0])}</span><div><div class="user-name">${esc(u.name)}</div><div class="user-role">${roles[u.role]}</div></div><button class="icon-btn" data-click="logout" title="Đăng xuất" aria-label="Đăng xuất">${icon('logout')}</button></div>`:'<button class="btn soft full" data-click="register">Tham gia Music Box '+icon('arrow')+'</button>';
  $('#page-name').textContent=links.find(([id])=>id===state.page)?.[2]||'Music Box';
}
async function refresh() { Object.assign(state, await api('bootstrap')); nav(); }
async function render() {
  const token=++renderId;
  state.page=location.hash.slice(1)||'rooms'; nav();
  $('#sidebar').classList.remove('open'); $('#menu-toggle').setAttribute('aria-expanded','false');
  const views={rooms:roomsPage,bookings:bookingsPage,loyalty:loyaltyPage,profile:profilePage,dashboard:dashboardPage,customers:customersPage,reports:reportsPage,manage:managePage,services:servicesPage,settings:settingsPage};
  if (state.page!=='rooms' && !state.user) { $('#content').innerHTML=empty('Không gian dành riêng cho bạn','Đăng nhập để quản lý lịch đặt, theo dõi điểm thưởng và lưu giữ những lần ghé thăm.','<button class="btn" data-click="login">Đăng nhập</button>'); return; }
  if (['dashboard','customers','reports'].includes(state.page) && state.user?.role==='customer' || ['manage','services','settings'].includes(state.page) && state.user?.role!=='admin') { $('#content').innerHTML=empty('Không có quyền truy cập','Chức năng này dành cho nhân viên hoặc quản trị viên.'); return; }
  $('#content').innerHTML='<div class="loading">Đang tải…</div>';
  try { const html=await (views[state.page]||roomsPage)(); if(token!==renderId)return; $('#content').innerHTML=html; if(state.page==='rooms') await displayRooms(); }
  catch(e) { if(token===renderId) $('#content').innerHTML=`<div class="panel error">${esc(e.message)} <button class="text-link" data-click="retry">Thử lại</button></div>`; }
}
function roomCard(r,free=null) {
  return `<article class="room-card"><div class="room-picture ${esc(r.theme)}"><img src="${esc(r.image_url||'/assets/room.svg')}" alt="Không gian ${esc(r.name)}" loading="lazy"><span class="room-number">STUDIO ${String(r.id).padStart(2,'0')}</span><span class="picture-badge"><i></i>${r.status!=='available'?(labels[r.status]):free===false?'Khung giờ đã kín':free===true?'Trống khung giờ đã chọn':'Không gian riêng tư'}</span></div><div class="room-body"><div class="room-title"><h3>${esc(r.name)}</h3><span>${esc(r.capacity)} khách</span></div><p class="room-description">${esc(r.description)}</p><div class="room-features"><span>${icon('users')}1 – ${esc(r.capacity)} người</span><span>${icon('headphones')}Âm thanh cao cấp</span><span>${icon('clock')}${r.opens_at.slice(0,5)} – ${r.closes_at.slice(0,5)}</span></div><div class="room-bottom"><div class="price">${money(r.hourly_rate)}<small> / giờ</small></div><button class="btn soft" data-click="book-room" data-id="${r.id}" ${r.status!=='available'?'disabled':''}>Xem & đặt phòng ${icon('arrow')}</button></div></div></article>`;
}
async function roomsPage() {
  const s=state.search||{date:dayPlus(state.today,1),time:'19:00',duration:'120',capacity:'0'};
  return `<section class="hero"><div class="hero-copy"><div class="eyebrow">${icon('music')} WELCOME TO MUSIC BOX</div><h1>Một căn phòng.<br><em>Ngàn cảm xúc.</em></h1><p>Bỏ lại những bộn bề, cùng nhau hát thật hết mình.<br>Không gian riêng tư cho mọi khoảnh khắc của bạn.</p><div class="hero-tags"><span>${icon('check')}Âm thanh chất lượng</span><span>${icon('check')}Đặt phòng dễ dàng</span><span>${icon('check')}Tích điểm mỗi lần ghé</span></div></div><div class="hero-art" aria-hidden="true"><span class="art-spark">✦</span><div class="vinyl"></div><div class="art-label"><span class="mini-wave">▂ ▅ ▃ ▇</span><div><strong>Your music. Your moment.</strong><span>Make a little room for yourself.</span></div></div></div></section><form id="search-form" class="search-panel">${field('NGÀY TRẢI NGHIỆM','date','date',s.date,`min="${state.today}" max="${dayPlus(state.today,90)}" required`)}${field('GIỜ BẮT ĐẦU','time','time',s.time,'required')}${selectField('THỜI LƯỢNG','duration',[[60,'1 giờ'],[90,'1 giờ 30 phút'],[120,'2 giờ'],[180,'3 giờ'],[240,'4 giờ']],s.duration)}${selectField('SỐ LƯỢNG KHÁCH','capacity',[[0,'Tất cả nhóm'],[2,'2 người'],[4,'4 người'],[6,'6 người'],[8,'8 người'],[12,'12 người']],s.capacity)}<button class="btn" type="submit">${icon('search')}Tìm phòng trống</button></form><div class="section-heading"><div><h2>Chọn không gian của bạn</h2><p>Mỗi căn phòng, một cá tính. Bạn muốn hát ở đâu hôm nay?</p></div><span class="count-label" id="room-count">${state.rooms.length} không gian</span></div><div class="room-grid" id="room-grid"></div><div class="info-strip"><div><span class="info-icon">${icon('gift')}</span><div><strong>Mỗi lần ghé thăm, thêm một niềm vui.</strong><p>Tích 1 điểm cho mỗi ${money(state.settings.earn_per)} thanh toán. Đổi điểm cho lần hẹn tiếp theo.</p></div></div><a class="text-link" href="#loyalty">Khám phá đặc quyền →</a></div>`;
}
function windowFrom(s) {
  const start=s.date+' '+s.time+':00';
  const d=new Date(start.replace(' ','T')+'+07:00'); d.setMinutes(d.getMinutes()+Number(s.duration));
  const endDate=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).format(d);
  const endTime=new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Ho_Chi_Minh',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(d);
  return {start,end:endDate+' '+endTime+':00'};
}
async function displayRooms() {
  const grid=$('#room-grid'); if(!grid)return;
  let rooms=state.rooms.filter(r=>r.status!=='inactive');
  if (state.search) {
    const s={...state.search}; const w=windowFrom(s);
    const free=await Promise.all(rooms.map(async r=> {
      if(Number(r.capacity)<Number(s.capacity) || r.status!=='available' || w.end.slice(0,10)!==s.date || s.time+':00'<r.opens_at || w.end.slice(11)>r.closes_at)return false;
      const busy=await api('availability',undefined,{room_id:r.id,date:s.date});
      return !busy.some(b=>b.starts_at<w.end && b.ends_at>w.start) && new Date(w.start.replace(' ','T')+'+07:00')>new Date();
    }));
    if(!grid.isConnected)return;
    rooms=rooms.filter((r,i)=>free[i]);
    grid.innerHTML=rooms.length?rooms.map(r=>roomCard(r,true)).join(''):empty('Chưa có phòng phù hợp','Thử giờ khác, giảm thời lượng hoặc chọn nhóm nhỏ hơn.','<button class="btn secondary" data-click="clear-search">Xem tất cả phòng</button>');
    if($('#room-count'))$('#room-count').textContent=rooms.length+' phòng trống';
  } else { grid.innerHTML=rooms.map(r=>roomCard(r)).join('') || empty('Chưa có phòng','Danh sách phòng sẽ được cập nhật sớm.'); }
}
function modal(title,html,narrow=false) {
  closeModal(); lastFocus=document.activeElement;
  $('#modal-root').innerHTML=`<div class="modal-overlay"><section class="modal ${narrow?'narrow':''}" role="dialog" aria-modal="true" aria-labelledby="modal-title"><div class="modal-head"><h2 id="modal-title">${esc(title)}</h2><button class="icon-btn" data-click="close-modal" aria-label="Đóng">×</button></div><div class="modal-body"><div class="error" id="modal-error" role="alert"></div>${html}</div></section></div>`;
  document.body.style.overflow='hidden';
  ($('input:not([type=hidden]),select,button', $('#modal-root'))||$('.modal')).focus();
}
function closeModal() { $('#modal-root').innerHTML=''; document.body.style.overflow=''; detailId=null; if(lastFocus?.isConnected)lastFocus.focus(); }
function authModal(register=false) {
  modal(register?'Bắt đầu câu chuyện của bạn':'Chào bạn, trở lại nhé!',`<p class="muted">${register?'Tham gia Music Box để đặt phòng và tích điểm mỗi lần ghé.':'Đăng nhập để tiếp tục những khoảnh khắc cùng âm nhạc.'}</p><form id="auth-form" data-mode="${register?'register':'login'}" class="stack">${register?field('Họ và tên','name','text','','required maxlength="100" autocomplete="name"')+field('Số điện thoại','phone','tel','','required maxlength="20" autocomplete="tel"'):''}${field('Địa chỉ email','email','email','','required maxlength="190" autocomplete="email"')}${field('Mật khẩu','password','password','','required minlength="10" maxlength="72" autocomplete="'+(register?'new-password':'current-password')+'"')}<button class="btn full" type="submit">${register?'Tạo tài khoản':'Đăng nhập'} ${icon('arrow')}</button></form><p class="auth-switch">${register?'Đã có tài khoản?':'Chưa có tài khoản?'} <button class="text-link" data-click="${register?'login':'register'}">${register?'Đăng nhập':'Đăng ký ngay'}</button></p>${state.demo?'<div class="demo-logins"><small>TÀI KHOẢN TRẢI NGHIỆM · MÔI TRƯỜNG DEMO</small><div><button class="btn small secondary" data-click="demo-login" data-role="customer">Khách hàng</button><button class="btn small secondary" data-click="demo-login" data-role="staff">Nhân viên</button><button class="btn small secondary" data-click="demo-login" data-role="admin">Admin</button></div></div>':''}`,true);
}
async function bookModal(id) {
  const r=state.rooms.find(r=>Number(r.id)===Number(id)); if(!r)return;
  const s=state.search||{date:dayPlus(state.today,1),time:'19:00',duration:'120'};
  modal('Đặt một khoảng thời gian cho bạn',`<div class="modal-booking"><div class="modal-room"><img src="${esc(r.image_url||'/assets/room.svg')}" alt="${esc(r.name)}"><h3>${esc(r.name)}</h3><p>${esc(r.description)}</p><span class="muted">${r.capacity} người · ${money(r.hourly_rate)} / giờ</span><div class="schedule" id="availability"><h4>Lịch phòng trong ngày</h4>Đang tải…</div></div><form id="booking-form" class="stack" data-id="${r.id}"><div class="form-grid">${field('Ngày đặt','date','date',s.date,`min="${state.today}" max="${dayPlus(state.today,90)}" required`)}${field('Giờ bắt đầu','time','time',s.time,`min="${r.opens_at.slice(0,5)}" max="${r.closes_at.slice(0,5)}" required`)}</div>${selectField('Thời lượng','duration',[[30,'30 phút'],[60,'1 giờ'],[90,'1 giờ 30 phút'],[120,'2 giờ'],[180,'3 giờ'],[240,'4 giờ'],[300,'5 giờ'],[360,'6 giờ']],s.duration)}${field(`Sử dụng điểm · Bạn có ${num(state.user?.points||0)} điểm`,'points','number','0',`min="0" max="${state.user?.points||0}" step="1" required`)}<label class="field"><span>Lời nhắn cho Music Box (tùy chọn)</span><textarea name="note" maxlength="500" placeholder="Bạn có yêu cầu gì đặc biệt không?"></textarea></label><div id="booking-quote"></div><button class="btn full" type="submit">${state.user?'Tạo lịch đặt':'Đăng nhập để đặt phòng'} ${icon('arrow')}</button><small class="subtle-note">Đặt cọc 30% để tự động xác nhận. Điểm giảm tối đa 50% tiền phòng. Các khoản thanh toán trên bản thử nghiệm được mô phỏng.</small></form></div>`);
  await updateQuote(true);
}
async function updateQuote(fetchSchedule=false) {
  const f=$('#booking-form');if(!f)return;
  const d=formData(f); const r=state.rooms.find(r=>Number(r.id)===Number(f.dataset.id));
  const amount=Math.ceil(r.hourly_rate*Number(d.duration)/60); const discount=Number(d.points||0)*state.settings.point_value;
  $('#booking-quote').innerHTML=`<div class="summary-row"><span>Tiền thuê phòng</span><strong>${money(amount)}</strong></div><div class="summary-row"><span>Ưu đãi từ điểm</span><strong>− ${money(discount)}</strong></div><div class="summary-row total"><span>Tổng dự kiến</span><span>${money(Math.max(0,amount-discount))}</span></div><div class="summary-row"><span>Đặt cọc (30%)</span><span>${money(Math.ceil(Math.max(0,amount-discount)*.3))}</span></div>`;
  if(fetchSchedule && d.date) {
    try {
      const list=await api('availability',undefined,{room_id:r.id,date:d.date});
      if($('#booking-form')!==f || f.elements.date.value!==d.date)return;
      $('#availability').innerHTML=`<h4>Lịch ngày ${esc(d.date.split('-').reverse().join('/'))} · ${r.opens_at.slice(0,5)}–${r.closes_at.slice(0,5)}</h4>${list.length?list.map(b=>`<span class="busy">${hm(b.starts_at)} – ${hm(b.ends_at)} · ${b.status==='maintenance'?'Bảo trì':'Đã có lịch đặt'}</span>`).join(''):'<span class="free">Còn trống trong giờ hoạt động ♫</span>'}`;
    } catch(e) { if($('#availability'))$('#availability').textContent=e.message; }
  }
}
function bookingsTable(list) {
  if(!list.length)return empty('Chưa có lịch đặt phù hợp','Các lịch đặt sẽ xuất hiện tại đây. Hãy chọn một không gian để bắt đầu.','<a class="btn soft" href="#rooms">Khám phá phòng</a>');
  return `<div class="table-wrap"><table><thead><tr><th>MÃ / PHÒNG</th><th>KHÁCH HÀNG</th><th>THỜI GIAN</th><th>TRẠNG THÁI</th><th>THANH TOÁN</th><th></th></tr></thead><tbody>${list.map(b=>`<tr><td><strong>${esc(b.room_name)}</strong><small>#MB${String(b.id).padStart(5,'0')}</small></td><td>${esc(b.customer_name)}<small>${esc(b.customer_phone)}</small></td><td>${dateTime(b.starts_at)}<small>Đến ${hm(b.ends_at)} · ${Math.round((new Date(b.ends_at.replace(' ','T')+'+07:00')-new Date(b.starts_at.replace(' ','T')+'+07:00'))/60000)} phút</small></td><td>${badge(b.status)}</td><td><strong>${money(b.estimated_total ?? b.total)}</strong><small>${b.status==='cancelled'?'Đã hủy / hoàn cọc':b.due?'Còn lại '+money(b.estimated_due ?? b.due):'Đã thanh toán đủ'}</small></td><td><button class="btn secondary small" data-click="booking-detail" data-id="${b.id}">Chi tiết →</button></td></tr>`).join('')}</tbody></table></div>`;
}
async function bookingsPage() {
  const list=await api('bookings');
  return pageHead(state.user.role==='customer'?'Những cuộc hẹn âm nhạc':'Quản lý lịch đặt','Lịch đặt, lịch sử ghé thăm và hóa đơn trong cùng một nơi.','<a class="btn" href="#rooms">+ Đặt phòng mới</a>')+`<div class="panel"><form id="booking-filter" class="toolbar">${selectField('Trạng thái','status',[['','Tất cả trạng thái'],...Object.keys(labels).slice(0,5).map(k=>[k,labels[k]])])}${field('Ngày sử dụng','date','date')}${field('Tên / email / số điện thoại','q','search','','placeholder="Tìm kiếm khách hàng…"')}<button class="btn secondary" type="submit">Lọc lịch đặt</button><button class="text-link" type="button" data-click="reset-bookings">Đặt lại</button></form><p class="subtle-note">Hiển thị tối đa 300 lịch đặt mới nhất.</p><div id="booking-list">${bookingsTable(list)}</div></div>`;
}
async function bookingDetail(id) {
  const b=await api('booking',undefined,{id}); detailModal(b);
}
function detailModal(b) {
  const staff=state.user.role!=='customer';
  let actions='';
  const liveNote=b.status==='checked_in'?`<div class="notice">Đang sử dụng: <strong data-elapsed="${esc(b.checked_in_at)}"></strong>. Tiền tạm tính có ${num(b.overtime_minutes)} phút quá giờ. Số tiền cuối cùng được chốt khi check-out; tải lại chi tiết để cập nhật dự toán.</div>`:'';
  if(['pending','confirmed'].includes(b.status)) {
    if(b.deposit_due>0)actions+=`<button class="btn" data-click="booking-action" data-op="deposit" data-id="${b.id}">Đặt cọc ${money(b.deposit_due)}</button>`;
    if(staff && b.status==='pending')actions+=`<button class="btn secondary" data-click="booking-action" data-op="confirm" data-id="${b.id}">Xác nhận lịch</button>`;
    if(staff && b.status==='confirmed')actions+=`<button class="btn" data-click="booking-action" data-op="checkin" data-id="${b.id}">Check-in</button>`;
    actions+=`<button class="btn danger" data-click="booking-action" data-op="cancel" data-id="${b.id}">Hủy lịch đặt</button>`;
  }
  if(staff && b.status==='checked_in')actions+=`<button class="btn" data-click="booking-action" data-op="checkout" data-id="${b.id}">Check-out & thu tiền</button>`;
  if(b.status==='completed')actions+='<button class="btn secondary" data-click="print">In hóa đơn</button>';
  modal(`Lịch đặt #MB${String(b.id).padStart(5,'0')}`,`<div class="detail-top"><h2>${esc(b.room_name)}</h2>${badge(b.status)}</div>${liveNote}<div class="detail-grid"><div><h3>Thông tin trải nghiệm</h3><dl class="detail-meta"><dt>Khách hàng</dt><dd>${esc(b.customer_name)}</dd><dt>Liên hệ</dt><dd>${esc(b.customer_phone)}<br>${esc(b.customer_email)}</dd><dt>Bắt đầu</dt><dd>${dateTime(b.starts_at)}</dd><dt>Kết thúc dự kiến</dt><dd>${dateTime(b.ends_at)}</dd><dt>Check-in</dt><dd>${dateTime(b.checked_in_at)}</dd><dt>Check-out</dt><dd>${dateTime(b.checked_out_at)}</dd><dt>Ghi chú</dt><dd>${esc(b.note)||'Không có'}</dd></dl></div><div><h3>Chi tiết hóa đơn</h3><div class="summary-row"><span>Tiền phòng</span><strong>${money(b.estimated_room_amount ?? b.room_amount)}</strong></div><div class="summary-row"><span>Dịch vụ</span><strong>${money(b.service_amount)}</strong></div><div class="summary-row"><span>Giảm ${num(b.redeemed_points)} điểm</span><strong>− ${money(b.discount_amount)}</strong></div><div class="summary-row total"><span>Tổng cộng</span><span>${money(b.estimated_total ?? b.total)}</span></div><div class="summary-row"><span>Đã thanh toán (sau hoàn tiền)</span><strong>${money(b.paid)}</strong></div><div class="summary-row"><span>Còn lại${b.status==='cancelled'?' (không thu)':''}</span><strong>${money(b.status==='cancelled'?0:b.due)}</strong></div><p class="subtle-note">${b.status==='completed'?'Đã tích '+num(b.earned_points)+' điểm.':'Tích điểm sau khi trả phòng và thanh toán đầy đủ.'}</p></div></div><div class="divider"></div><h3>Dịch vụ sử dụng</h3>${b.services.length?b.services.map(s=>`<div class="service-line"><div>${esc(s.name)} × ${s.quantity}<br><small>${money(s.unit_price)} / phần · ${{ordered:'Chờ phục vụ',served:'Đã phục vụ',cancelled:'Đã hủy'}[s.status]}</small></div><div class="line-actions"><strong>${s.status==='cancelled'?'—':money(s.unit_price*s.quantity)}</strong>${staff && s.status==='ordered' && b.status==='checked_in'?`<button class="text-link" data-click="service-status" data-id="${b.id}" data-line="${s.id}" data-status="served">Đã phục vụ</button><button class="text-link negative" data-click="service-status" data-id="${b.id}" data-line="${s.id}" data-status="cancelled">Hủy</button>`:''}</div></div>`).join(''):'<p class="muted">Chưa có dịch vụ bổ sung.</p>'}${staff && b.status==='checked_in'?`<form id="add-service-form" class="inline-form" data-id="${b.id}">${selectField('Thêm dịch vụ','service_id',state.services.map(s=>[s.id,s.name+' · '+money(s.price)]))}${field('Số lượng','quantity','number','1','min="1" max="100" required class="quantity-input"')}<button class="btn small secondary" type="submit">+ Thêm</button></form>`:''}<div class="divider"></div><h3>Lịch sử thanh toán</h3>${b.payments.length?b.payments.map(p=>`<div class="service-line"><div>${{deposit:'Đặt cọc',settlement:'Thanh toán còn lại',refund:'Hoàn tiền'}[p.type]} · ${{cash:'Tiền mặt',transfer:'Chuyển khoản',demo:'Mô phỏng'}[p.method]}<br><small>${dateTime(p.created_at)}</small></div><strong>${p.type==='refund'?'− ':''}${money(p.amount)}</strong></div>`).join(''):'<p class="muted">Chưa ghi nhận thanh toán.</p>'}${staff && ['confirmed','checked_in'].includes(b.status)?`<div class="divider"></div><form id="extend-form" data-id="${b.id}" class="inline-form">${field('Gia hạn đến','ends_at','datetime-local',b.ends_at.replace(' ','T').slice(0,16),'required')}<button class="btn secondary" type="submit">Gia hạn</button></form>`:''}${staff && ['pending','confirmed','checked_in'].includes(b.status)?`<div class="divider"></div>${selectField('Phương thức thu tiền','payment-method',[['cash','Tiền mặt'],['transfer','Chuyển khoản (nhân viên xác nhận)'],['demo','Mô phỏng']])}`:''}<div class="actions">${actions}</div><p class="check-note">Thanh toán mô phỏng / nhân viên ghi nhận, chưa kết nối ngân hàng. Hủy trước giờ nhận phòng được hoàn cọc và trả điểm trong hệ thống. Trả sớm tính đủ giờ đã đặt; quá giờ tính thêm từng phút theo đơn giá đã chốt.</p>`);
  detailId=b.id;
  updateTimers();
}
async function loyaltyPage() {
  const ledger=await api('points');
  return pageHead('Giai điệu của những đặc quyền','Mỗi lần ghé thăm là một lần tích lũy niềm vui.')+`<div class="two-col"><div class="loyalty-card"><span class="section-caption">MUSIC BOX REWARDS</span><strong>${num(state.user.points)} <small>điểm</small></strong><p>Cảm ơn ${esc(state.user.name)} vì đã chọn Music Box.</p><small>Giá trị quy đổi hiện tại: ${money(state.user.points*state.settings.point_value)}</small></div><div class="panel"><h2>Hát hết mình. Nhận thêm ưu đãi.</h2><p class="muted">Mỗi ${money(state.settings.earn_per)} thanh toán hợp lệ = 1 điểm.<br>1 điểm = ${money(state.settings.point_value)} giảm giá cho lần đặt sau.</p><p class="subtle-note">Điểm được cộng khi hoàn tất check-out và thanh toán. Bạn có thể dùng điểm để giảm tối đa 50% tiền thuê phòng khi tạo lịch đặt. Điểm đã dùng được giữ ngay và sẽ hoàn lại nếu lịch bị hủy. Không tích điểm trên phần được giảm giá.</p><a class="btn soft" href="#rooms">Đặt phòng & nhận ưu đãi →</a></div></div><div class="panel"><h2>Lịch sử điểm thưởng</h2>${ledger.length?`<div class="table-wrap"><table><thead><tr><th>THỜI GIAN</th><th>NỘI DUNG</th><th>ĐIỂM</th><th>SỐ DƯ SAU GIAO DỊCH</th></tr></thead><tbody>${ledger.map(p=>`<tr><td>${dateTime(p.created_at)}</td><td>${{earn:'Tích điểm',redeem:'Sử dụng điểm',restore:'Hoàn điểm'}[p.kind]} · ${esc(p.room_name)}<small>#MB${String(p.booking_id).padStart(5,'0')}</small></td><td class="${p.delta>0?'positive':'negative'}">${p.delta>0?'+':''}${num(p.delta)}</td><td>${num(p.balance_after)}</td></tr>`).join('')}</tbody></table></div>`:empty('Điểm thưởng đang chờ bạn','Hoàn tất trải nghiệm đầu tiên để bắt đầu tích điểm.')}</div>`;
}
async function profilePage() {
  const u=state.user;
  return pageHead('Thông tin của bạn','Giữ liên lạc, để không bỏ lỡ những cuộc hẹn âm nhạc.')+`<div class="two-col"><div class="panel"><h2>Thông tin cá nhân</h2><form id="profile-form" class="stack">${field('Họ và tên','name','text',u.name,'required maxlength="100"')}${field('Số điện thoại','phone','tel',u.phone,'required maxlength="20"')}${field('Email','email','email',u.email,'disabled')}<div class="divider"></div><h3>Đổi mật khẩu (tùy chọn)</h3>${field('Mật khẩu hiện tại','current_password','password','','autocomplete="current-password" maxlength="72"')}${field('Mật khẩu mới','password','password','','minlength="10" maxlength="72" autocomplete="new-password"')}<div class="error" role="alert"></div><button class="btn" type="submit">Lưu thông tin</button></form></div><div class="panel"><span class="avatar">${esc(u.name[0])}</span><h2>${esc(u.name)}</h2><p class="muted">${roles[u.role]} · Thành viên từ ${dateTime(u.created_at)}</p><div class="divider"></div><p class="muted">Số dư điểm: <strong>${num(u.points)}</strong></p><button class="btn secondary" data-click="logout">${icon('logout')}Đăng xuất</button></div></div>`;
}
async function dashboardPage() {
  const reports=await api('reports'); const list=await api('bookings',undefined,{date:state.today});
  return pageHead('Một ngày mới, những giai điệu mới','Cùng Music Box chăm sóc từng trải nghiệm của khách hàng.','<a class="btn" href="#bookings">Quản lý lịch đặt →</a>')+stats(reports)+`<div class="panel"><div class="section-heading"><div><h2>Lịch sử dụng hôm nay</h2><p>${esc(state.today)} · ${list.length} lịch đặt</p></div><a class="text-link" href="#bookings">Xem tất cả →</a></div>${bookingsTable(list)}</div><div class="panel"><h2>Trạng thái phòng hiện tại</h2><div class="catalog-grid">${state.rooms.map(r=>`<div class="catalog-item"><h3>${esc(r.name)}</h3>${badge(r.occupied?'checked_in':r.status)}<p class="subtle-note">${r.capacity} người · ${money(r.hourly_rate)} / giờ</p></div>`).join('')}</div></div>`;
}
function stats(r) { return `<div class="stats-grid"><div class="stat"><span>THỰC THU TRONG KỲ</span><strong>${money(r.revenue)}</strong><small>Sau hoàn tiền · gồm tiền cọc</small></div><div class="stat"><span>LƯỢT ĐẶT PHÒNG</span><strong>${num(r.bookings)}</strong><small>Theo ngày bắt đầu sử dụng</small></div><div class="stat"><span>LƯỢT KHÁCH GHÉ THĂM</span><strong>${num(r.visits)}</strong><small>${num(r.customers)} tài khoản khách đã check-in</small></div><div class="stat"><span>TỶ LỆ HỦY</span><strong>${num(r.cancellation_rate)}%</strong><small>${num(r.cancelled)} lịch đã hủy trong kỳ</small></div></div>`; }
async function reportsPage(filter={}) {
  const r=await api('reports',undefined,filter); const max=Math.max(1,...r.daily.map(d=>Math.abs(Number(d.revenue))));
  return pageHead('Những con số biết kể chuyện','Theo dõi doanh thu và hoạt động kinh doanh của Music Box.')+`<form id="report-form" class="toolbar">${field('Từ ngày','from','date',r.from,'required')}${field('Đến ngày','to','date',r.to,'required')}<button class="btn" type="submit">Xem báo cáo</button><button class="btn secondary" type="button" data-click="export-report">Xuất CSV</button></form><div id="report-results">${stats(r)}<div class="two-col"><div class="panel"><h2>Thực thu theo ngày</h2><p class="subtle-note">Tiền cọc + thanh toán còn lại − tiền hoàn, theo ngày ghi nhận.</p>${r.daily.length?`<div class="chart">${r.daily.map(d=>`<div class="chart-item"><span>${num(d.revenue/1000)}k</span><div class="chart-bar" data-value="${Math.round(Math.abs(d.revenue)/max*100)}" title="${esc(d.day)}: ${money(d.revenue)}"></div><small>${d.day.slice(8,10)}/${d.day.slice(5,7)}</small></div>`).join('')}</div>`:empty('Chưa có giao dịch','Thực thu được ghi nhận ngay khi có thanh toán.')}<p class="subtle-note">Đã hoàn ${money(r.refunds)} trong kỳ. Biểu đồ hiển thị độ lớn; giá trị âm thể hiện ngày hoàn tiền nhiều hơn tiền thu.</p></div><div class="panel"><h2>Hiệu quả theo phòng</h2><table><thead><tr><th>PHÒNG</th><th>LƯỢT ĐẶT</th><th>HOÀN TẤT</th></tr></thead><tbody>${r.rooms.map(s=>`<tr><td>${esc(s.name)}</td><td>${num(s.bookings)}</td><td>${num(s.completed)}</td></tr>`).join('')}</tbody></table></div></div></div>`;
}
function customersTable(list) {
  return list.length?`<div class="table-wrap"><table><thead><tr><th>KHÁCH HÀNG</th><th>LIÊN HỆ</th><th>VAI TRÒ</th><th>ĐÃ GHÉ</th><th>ĐIỂM</th><th></th></tr></thead><tbody>${list.map(u=>`<tr><td><strong>${esc(u.name)}</strong><small>${Number(u.active)?'Đang hoạt động':'Đã khóa'}</small></td><td>${esc(u.email)}<small>${esc(u.phone)}</small></td><td>${roles[u.role]}</td><td>${num(u.visits)} lần</td><td>${num(u.points)}</td><td><button class="btn small secondary" data-click="customer-history" data-id="${u.id}">Lịch sử</button> ${state.user.role==='admin'?`<button class="btn small soft" data-click="edit-account" data-id="${u.id}">Phân quyền</button>`:''}</td></tr>`).join('')}</tbody></table></div>`:empty('Không tìm thấy khách hàng','Thử tìm bằng tên, email hoặc số điện thoại khác.');
}
async function customersPage() { const list=await api('customers'); return pageHead('Khách hàng & những lần ghé','Tra cứu thông tin, lịch sử sử dụng và số dư điểm thưởng.')+`<div class="panel"><form id="customer-filter" class="toolbar">${field('Tên / email / số điện thoại','q','search','','placeholder="Tìm kiếm khách hàng…"')}<button class="btn secondary" type="submit">Tìm kiếm</button></form><p class="subtle-note">Tối đa 300 tài khoản mới nhất phù hợp.</p><div id="customer-list">${customersTable(list)}</div></div>`; }
async function managePage() {
  const catalog=await api('admin_catalog');
  return pageHead('Không gian Music Box','Quản lý phòng, giá thuê và thời gian hoạt động.','<button class="btn" data-click="edit-room" data-id="0">+ Thêm phòng</button>')+`<div class="panel"><div class="table-wrap"><table><thead><tr><th>PHÒNG</th><th>SỨC CHỨA</th><th>GIÁ / GIỜ</th><th>GIỜ HOẠT ĐỘNG</th><th>TRẠNG THÁI</th><th></th></tr></thead><tbody>${state.rooms.map(r=>`<tr><td><div class="room-admin"><img src="${esc(r.image_url||'/assets/room.svg')}" alt=""><strong>${esc(r.name)}</strong></div></td><td>${r.capacity} người</td><td>${money(r.hourly_rate)}</td><td>${r.opens_at.slice(0,5)} – ${r.closes_at.slice(0,5)}</td><td>${badge(r.status)}</td><td><button class="btn secondary small" data-click="edit-room" data-id="${r.id}">Chỉnh sửa</button></td></tr>`).join('')}</tbody></table></div></div><div class="panel"><div class="section-heading"><div><h2>Lịch bảo trì & ngừng nhận đặt</h2><p>Khung giờ bảo trì được chặn khỏi lịch trống.</p></div><button class="btn secondary small" data-click="maintenance">+ Thêm lịch</button></div>${catalog.maintenance.length?`<div class="table-wrap"><table><thead><tr><th>PHÒNG</th><th>BẮT ĐẦU</th><th>KẾT THÚC</th><th>LÝ DO</th><th></th></tr></thead><tbody>${catalog.maintenance.map(m=>`<tr><td>${esc(m.room_name)}</td><td>${dateTime(m.starts_at)}</td><td>${dateTime(m.ends_at)}</td><td>${esc(m.reason)}</td><td><button class="text-link negative" data-click="delete-maintenance" data-id="${m.id}">Xóa lịch</button></td></tr>`).join('')}</tbody></table></div>`:empty('Chưa có lịch bảo trì','Thiết lập thời gian ngừng nhận đặt để chủ động chuẩn bị phòng.')}</div>`;
}
function editRoom(id) {
  const r=state.rooms.find(r=>Number(r.id)===Number(id))||{id:0,name:'',description:'',capacity:4,hourly_rate:120000,opens_at:'09:00',closes_at:'23:00',status:'available',theme:'violet',image_url:''};
  modal(id?'Chỉnh sửa phòng':'Thêm không gian mới',`<form id="room-form" data-id="${r.id}" class="stack"><div class="form-grid">${field('Tên phòng','name','text',r.name,'required maxlength="100"')}${field('Sức chứa','capacity','number',r.capacity,'required min="1" max="50"')}${field('Giá thuê / giờ (₫)','hourly_rate','number',r.hourly_rate,'required min="1000" max="10000000"')}${selectField('Trạng thái','status',[['available','Nhận đặt phòng'],['maintenance','Bảo trì'],['inactive','Ngừng hoạt động']],r.status)}${field('Mở cửa','opens_at','time',r.opens_at.slice(0,5),'required')}${field('Đóng cửa','closes_at','time',r.closes_at.slice(0,5),'required')}${selectField('Tông màu','theme',[['violet','Tím'],['blue','Xanh'],['orange','Cam'],['pink','Hồng']],r.theme)}${field('URL hình ảnh (HTTPS hoặc /assets/…)','image_url','text',r.image_url,'maxlength="500"')}</div><label class="field"><span>Mô tả</span><textarea name="description" maxlength="4000" required>${esc(r.description)}</textarea></label><p class="subtle-note">Ảnh trống sẽ dùng hình minh họa mặc định. Đổi giá không ảnh hưởng các lịch đặt đã tạo.</p><button class="btn" type="submit">Lưu thông tin phòng</button></form>`);
}
async function servicesPage() {
  const {services}=await api('admin_catalog'); state.catalogServices=services;
  return pageHead('Thêm hương vị cho cuộc vui','Bảng giá dịch vụ bổ sung. Giá đã bán được giữ nguyên trong hóa đơn.','<button class="btn" data-click="edit-service" data-id="0">+ Thêm dịch vụ</button>')+`<div class="catalog-grid">${services.map(s=>`<div class="panel catalog-item"><span class="info-icon">${icon('music')}</span><h3>${esc(s.name)}</h3><div class="price">${money(s.price)}</div>${badge(Number(s.active)?'available':'inactive')}<div><button class="btn secondary small" data-click="edit-service" data-id="${s.id}">Chỉnh sửa</button></div></div>`).join('')}</div>`;
}
async function settingsPage() { return pageHead('Thiết lập cho những lần ghé tiếp theo','Cấu hình chương trình tích điểm của Music Box.')+`<div class="two-col"><div class="panel"><h2>Chương trình điểm thưởng</h2><form id="settings-form" class="stack">${field('Số tiền thanh toán để tích 1 điểm (₫)','earn_per','number',state.settings.earn_per,'required min="1000" max="10000000"')}${field('Giá trị giảm giá của 1 điểm (₫)','point_value','number',state.settings.point_value,'required min="1" max="100000"')}<div class="error" role="alert"></div><button class="btn" type="submit">Lưu cấu hình</button></form></div><div class="panel"><h2>Quy tắc áp dụng</h2><p class="muted">Tỷ lệ tích điểm được chốt tại thời điểm tạo lịch đặt. Thay đổi cấu hình chỉ áp dụng cho các lịch tạo sau đó.</p><p class="subtle-note">Điểm giảm tối đa 50% tiền thuê phòng. Không tích điểm trên số tiền đã giảm. Điểm được cộng một lần sau khi khách check-out và thanh toán đủ; lịch hủy được hoàn điểm và hoàn tiền mô phỏng.</p><div class="notice">Bản đồ án dùng thanh toán mô phỏng và ghi nhận thủ công. Không nhập thông tin thẻ hoặc thông tin ngân hàng thật.</div></div></div>`; }

async function submit(form) {
  const d=formData(form); const id=form.dataset.id;
  switch(form.id) {
    case 'search-form': state.search=d; await displayRooms(); return;
    case 'auth-form': await api(form.dataset.mode,d); await refresh(); closeModal(); await render(); toast(form.dataset.mode==='register'?'Chào mừng bạn đến với Music Box!':'Đăng nhập thành công.'); return;
    case 'booking-form': {
      if(!state.user){ authModal();return; }
      const w=windowFrom(d); const b=await api('book',{room_id:id,starts_at:w.start,ends_at:w.end,points:d.points,note:d.note});
      await refresh(); toast('Đã tạo lịch đặt. Đặt cọc để tự động xác nhận.'); location.hash='bookings'; detailModal(b); return;
    }
    case 'booking-filter': $('#booking-list').innerHTML=bookingsTable(await api('bookings',undefined,d)); return;
    case 'customer-filter': $('#customer-list').innerHTML=customersTable(await api('customers',undefined,d)); return;
    case 'profile-form': await api('profile',d); await refresh(); toast('Đã lưu thông tin cá nhân.'); return;
    case 'add-service-form': detailModal(await api('booking_action',{...d,id,operation:'service'})); toast('Đã thêm dịch vụ.'); return;
    case 'extend-form': detailModal(await api('booking_action',{...d,id,operation:'extend'})); toast('Đã gia hạn thời gian.'); return;
    case 'room-form': await api('room_save',{...d,id}); break;
    case 'maintenance-form': await api('maintenance_save',d); break;
    case 'service-form': await api('service_save',{...d,id}); break;
    case 'account-form': await api('account_save',{...d,id}); break;
    case 'settings-form': await api('settings_save',d); await refresh(); toast('Đã lưu cấu hình tích điểm.'); return;
    case 'report-form': $('#content').innerHTML=await reportsPage(d); chartHeights(); return;
    default:return;
  }
  await refresh(); closeModal(); await render(); toast('Đã lưu thay đổi.');
}
function chartHeights() { $$('.chart-bar').forEach(b=>b.style.height=b.dataset.value+'%'); }
document.addEventListener('submit',async e=> {
  if(!(e.target instanceof HTMLFormElement))return; e.preventDefault();
  const form=e.target; const btn=$('button[type="submit"]',form); const error=$('.error',form)||$('#modal-error'); if(error)error.textContent='';
  if(btn)btn.disabled=true;
  try{await submit(form);}catch(err){if(error?.isConnected)error.textContent=err.message;else toast(err.message,true);}finally{if(btn?.isConnected)btn.disabled=false;}
});
document.addEventListener('change',e=>{if(e.target.closest('#booking-form'))updateQuote(e.target.name==='date');});
document.addEventListener('input',e=>{if(e.target.closest('#booking-form') && e.target.name==='points')updateQuote();});
document.addEventListener('click',async e=> {
  const el=e.target.closest('[data-click]'); if(!el)return;
  const c=el.dataset.click,id=el.dataset.id; const error=$('#modal-error'); if(error)error.textContent='';
  try {
    switch(c) {
      case 'login':authModal();break;
      case 'register':authModal(true);break;
      case 'close-modal':closeModal();break;
      case 'retry':await render();break;
      case 'logout':await api('logout',{}); await refresh(); closeModal(); location.hash='rooms'; await render(); toast('Đã đăng xuất. Hẹn gặp lại bạn!');break;
      case 'demo-login':el.disabled=true;await api('login',{email:el.dataset.role+'@musicbox.local',password:'MusicBox@123'});await refresh();closeModal();location.hash=state.user.role==='customer'?'rooms':'dashboard';await render();break;
      case 'book-room':await bookModal(id);break;
      case 'clear-search':state.search=null;await render();break;
      case 'booking-detail':await bookingDetail(id);break;
      case 'reset-bookings':await render();break;
      case 'booking-action': {
        const op=el.dataset.op;
        if(op==='cancel' && !confirm('Hủy lịch đặt này? Tiền cọc được hoàn mô phỏng và điểm đã dùng được trả lại.'))return;
        if(op==='checkout' && !confirm('Xác nhận khách trả phòng và đã thanh toán toàn bộ số tiền còn lại?'))return;
        el.disabled=true;
        const method=$('[name="payment-method"]')?.value||'demo';
        const b=await api('booking_action',{id,operation:op,method});
        await refresh(); await render(); detailModal(b); toast('Đã cập nhật lịch đặt.');break;
      }
      case 'service-status':el.disabled=true;detailModal(await api('booking_action',{id,operation:'service_status',line_id:el.dataset.line,status:el.dataset.status}));break;
      case 'print':window.print();break;
      case 'customer-history': {
        const list=await api('customer_history',undefined,{id}); modal('Lịch sử ghé thăm & đặt phòng',bookingsTable(list));break;
      }
      case 'edit-account': {
        const list=await api('customers');const u=list.find(u=>Number(u.id)===Number(id));if(!u)throw new Error('Hãy tìm lại tài khoản trong danh sách.');
        modal('Phân quyền · '+u.name,`<form id="account-form" data-id="${u.id}" class="stack">${selectField('Vai trò','role',Object.entries(roles),u.role)}${selectField('Tình trạng tài khoản','active',[[1,'Đang hoạt động'],[0,'Khóa tài khoản']],u.active)}<p class="subtle-note">Khóa tài khoản sẽ chặn truy cập ở yêu cầu kế tiếp.</p><button class="btn" type="submit">Lưu phân quyền</button></form>`,true);break;
      }
      case 'edit-room':editRoom(Number(id));break;
      case 'maintenance':modal('Thêm lịch bảo trì',`<form id="maintenance-form" class="stack">${selectField('Phòng','room_id',state.rooms.map(r=>[r.id,r.name]))}${field('Bắt đầu','starts_at','datetime-local','','required')}${field('Kết thúc','ends_at','datetime-local','','required')}${field('Lý do','reason','text','','required maxlength="255"')}<button class="btn" type="submit">Lưu lịch bảo trì</button></form>`,true);break;
      case 'delete-maintenance':if(!confirm('Xóa lịch bảo trì và mở lại khung giờ này?'))return;await api('maintenance_delete',{id});await render();toast('Đã xóa lịch bảo trì.');break;
      case 'edit-service': {
        const s=state.catalogServices.find(s=>Number(s.id)===Number(id))||{id:0,name:'',price:15000,active:1};
        modal(id==='0'?'Thêm dịch vụ':'Chỉnh sửa dịch vụ',`<form id="service-form" data-id="${s.id}" class="stack">${field('Tên dịch vụ','name','text',s.name,'required maxlength="100"')}${field('Giá bán (₫)','price','number',s.price,'required min="0" max="10000000"')}${selectField('Trạng thái','active',[[1,'Đang bán'],[0,'Ngừng bán']],s.active)}<button class="btn" type="submit">Lưu dịch vụ</button></form>`,true);break;
      }
      case 'export-report': {
        const r=await api('reports',undefined,formData($('#report-form')));
        const csv='\uFEFFNgày,Thực thu (VND)\r\n'+r.daily.map(d=>d.day+','+d.revenue).join('\r\n');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download=`musicbox-${r.from}-${r.to}.csv`;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);break;
      }
    }
  } catch(err) { const box=$('#modal-error'); if(box){box.textContent=err.message;box.scrollIntoView({block:'nearest'});}else toast(err.message,true); }
  finally { if(el.isConnected)el.disabled=false; }
});
document.addEventListener('keydown',e=> {
  const m=$('.modal');if(!m)return;
  if(e.key==='Escape')closeModal();
  if(e.key==='Tab') {
    const focusable=$$('button:not(:disabled),[href],input:not(:disabled),select,textarea',m);const first=focusable[0],last=focusable.at(-1);
    if(e.shiftKey && document.activeElement===first){e.preventDefault();last?.focus();}else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first?.focus();}
  }
});
$('#menu-toggle').addEventListener('click',()=>{const open=$('#sidebar').classList.toggle('open');$('#menu-toggle').setAttribute('aria-expanded',String(open));});
window.addEventListener('hashchange',()=>render().then(chartHeights));
refresh().then(()=>render()).then(chartHeights).catch(e=>{$('#content').innerHTML=`<div class="panel error">${esc(e.message)}<p>Kiểm tra cơ sở dữ liệu và chạy migrate/seed theo README.</p><button class="btn secondary" data-click="retry">Thử lại</button></div>`;});

function updateTimers() {
  $$('[data-elapsed]').forEach(el=>{
    const seconds=Math.max(0,Math.floor((Date.now()-new Date(el.dataset.elapsed.replace(' ','T')+'+07:00').getTime())/1000));
    el.textContent=Math.floor(seconds/3600)+' giờ '+Math.floor(seconds%3600/60)+' phút';
  });
}
setInterval(updateTimers,30000);
