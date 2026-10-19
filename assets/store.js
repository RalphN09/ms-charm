'use strict';
// Device-local popup preferences never decide promotion eligibility.
const welcomeDialog=document.querySelector('[data-welcome-popup]');
if(welcomeDialog){
  const welcomeStorageKey='mscharm.welcome.seen.v1';
  const welcomeSeen=()=>{try{if(localStorage.getItem(welcomeStorageKey)==='1')return true;}catch{}return document.cookie.split(';').some(cookie=>cookie.trim()==='mscharm_welcome_seen=1');};
  const rememberWelcome=()=>{try{localStorage.setItem(welcomeStorageKey,'1');}catch{}document.cookie='mscharm_welcome_seen=1; Max-Age=31536000; Path=/; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');};
  const showWelcome=()=>{
    if(welcomeDialog.open||document.querySelector('dialog[open]'))return;
    if(typeof welcomeDialog.showModal!=='function'){location.assign('/register?welcome=1');return;}
    rememberWelcome();welcomeDialog.showModal();
  };
  const dismissWelcome=()=>{rememberWelcome();welcomeDialog.close();};
  document.querySelectorAll('[data-open-welcome]').forEach(control=>control.addEventListener('click',event=>{event.preventDefault();showWelcome();}));
  welcomeDialog.querySelectorAll('[data-close-welcome]').forEach(button=>button.addEventListener('click',dismissWelcome));
  welcomeDialog.addEventListener('cancel',rememberWelcome);
  welcomeDialog.addEventListener('keydown',event=>{
    if(event.key!=='Tab')return;
    const controls=Array.from(welcomeDialog.querySelectorAll('a[href],button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),[tabindex="0"]')).filter(control=>control.getClientRects().length);
    const first=controls[0],last=controls[controls.length-1];
    if(event.shiftKey && document.activeElement===first){event.preventDefault();last.focus();}
    else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus();}
  });
  welcomeDialog.addEventListener('click',event=>{if(event.target===welcomeDialog){const rect=welcomeDialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)dismissWelcome();}});
  const autoWelcome=()=>{if(!welcomeSeen() && typeof welcomeDialog.showModal==='function')showWelcome();};
  if(document.querySelector('[data-offer-popup]'))document.addEventListener('offers:empty',autoWelcome,{once:true});
  else window.setTimeout(autoWelcome,700);
}
// On returning to a page, refresh status using server time rather than the device clock.
let refreshingWelcome=false;
async function refreshWelcomeStatus(){
  const panels=Array.from(document.querySelectorAll('[data-welcome-status]'));
  if(!panels.length||refreshingWelcome)return;
  refreshingWelcome=true;
  try{const response=await fetch('/api/welcome',{headers:{Accept:'application/json'},cache:'no-store'});if(response.ok){const offer=await response.json();if(panels.some(panel=>panel.dataset.welcomeStatus!==offer.status))location.reload();}}
  catch{}finally{refreshingWelcome=false;}
}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refreshWelcomeStatus();});
window.addEventListener('pageshow',event=>{if(event.persisted)refreshWelcomeStatus();});
function fitShopFilters(dropdown){
  if(!dropdown.open)return;
  let space=window.innerHeight-dropdown.querySelector('summary').getBoundingClientRect().bottom-24;
  if(space<200){dropdown.scrollIntoView({block:'start'});space=window.innerHeight-dropdown.querySelector('summary').getBoundingClientRect().bottom-24;}
  dropdown.querySelector('.shop-filter-panel').style.maxHeight=Math.max(160,Math.min(window.innerHeight*.7,space))+'px';
}
document.querySelectorAll('[data-shop-filters]').forEach(dropdown=>dropdown.addEventListener('toggle',()=>fitShopFilters(dropdown)));
window.addEventListener('resize',()=>document.querySelectorAll('[data-shop-filters][open]').forEach(fitShopFilters));
document.addEventListener('click',event=>{
  document.querySelectorAll('[data-shop-filters][open]').forEach(dropdown=>{if(!dropdown.contains(event.target))dropdown.open=false;});
});
document.addEventListener('keydown',event=>{
  if(event.key==='Escape')document.querySelectorAll('[data-shop-filters][open]').forEach(dropdown=>{dropdown.open=false;dropdown.querySelector('summary').focus();});
});
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
function notify(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message; toast.hidden = false;
  window.clearTimeout(notify.timer); notify.timer = window.setTimeout(() => { toast.hidden = true; }, 5000);
}
async function updateBagState(body) {
    const response = await fetch('/api/cart', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken}, body: JSON.stringify(body)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || 'Please try again.');
    document.querySelectorAll('[data-cart-count]').forEach(el => { el.textContent = result.count; });
    return result;
}
async function changeBag(button, body, reload = false) {
  button.disabled = true;
  try {
    await updateBagState(body);
    if (button.hasAttribute('data-buy')) window.location.assign('/checkout');
    else if (reload) window.location.reload();
    else notify('A little charm, added to your bag.');
  } catch (error) { notify(error.message || 'Unable to update your bag. Please try again.'); }
  finally { button.disabled = false; }
}
document.addEventListener('click', event => {
  const add = event.target.closest('[data-add]');
  if (add) {
    const quantity = add.dataset.quantity ? Number(document.querySelector(add.dataset.quantity).value) : 1;
    if (!Number.isInteger(quantity) || quantity < 1) { notify('Please choose a valid quantity.'); return; }
    changeBag(add, {product_id: add.dataset.add, quantity, mode: 'add'});
  }
  const update = event.target.closest('[data-cart-update]');
  if (update) changeBag(update, {product_id: update.dataset.cartUpdate, quantity: Number(update.dataset.value), mode: 'set'}, true);
  const thumb = event.target.closest('[data-image]');
  if (thumb) document.getElementById('product-main-image').src = thumb.dataset.image;
  const menu = event.target.closest('[data-menu]');
  if (menu) { const expanded = menu.getAttribute('aria-expanded') === 'true'; menu.setAttribute('aria-expanded', String(!expanded)); document.getElementById('main-nav').classList.toggle('open', !expanded); }
});
document.addEventListener('submit', event => {
  if(event.defaultPrevented)return;
  const form = event.target;
  if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
  if (!form.checkValidity()) return;
  const submit = form.querySelector('button[type="submit"], button:not([type])');
  if (submit) { window.setTimeout(() => { submit.disabled = true; }, 0); }
});

// Optional browser-native tools use the same authenticated, CSRF-protected actions.
if (document.modelContext?.registerTool && !document.body.classList.contains('admin-body')) {
  const lifecycle = new AbortController();
  const register = tool => {
    try { Promise.resolve(document.modelContext.registerTool(tool, {signal: lifecycle.signal})).catch(() => {}); }
    catch { /* Unsupported browser implementations do not affect the storefront. */ }
  };
  register({name:'read_shopping_bag',title:'Read shopping bag',description:'Read the current shopping bag, current database prices, stock availability, and totals. Does not place an order.',inputSchema:{type:'object',properties:{},additionalProperties:false},annotations:{readOnlyHint:true,untrustedContentHint:true},async execute(input){
    if (!input || typeof input !== 'object' || Array.isArray(input) || Object.keys(input).length) throw new Error('Provide an empty object.');
    const response=await fetch('/api/cart'); if(!response.ok)throw new Error('Unable to read your bag.');
    const bag=await response.json();
    return {items:bag.items.map(line=>({productId:line.product.id,name:line.product.name,quantity:line.quantity,unitPriceCents:Number(line.product.sale_price??line.product.price),available:line.available})),subtotalCents:bag.subtotal,discountCents:bag.discount,discountedSubtotalCents:bag.discounted_subtotal,deliveryCents:bag.delivery,taxCents:bag.tax,totalCents:bag.total};
  }});
  if(document.querySelector('[data-add]')) register({name:'add_product_to_bag',title:'Add product to bag',description:'Add an available product to the shopping bag and update the visible bag count. Does not checkout or create an order.',inputSchema:{type:'object',properties:{productId:{type:'string',pattern:'^[a-f0-9]{32}$'},quantity:{type:'integer',minimum:1,maximum:99}},required:['productId','quantity'],additionalProperties:false},annotations:{readOnlyHint:false,untrustedContentHint:false},async execute(input){
    if(!input || typeof input!=='object' || !/^[a-f0-9]{32}$/.test(input.productId) || !Number.isInteger(input.quantity) || input.quantity<1 || input.quantity>99 || Object.keys(input).some(key=>!['productId','quantity'].includes(key)))throw new Error('Provide a valid product ID and a quantity from 1 to 99.');
    const result=await updateBagState({product_id:input.productId,quantity:input.quantity,mode:'add'});notify('A little charm, added to your bag.');return {count:result.count,total:result.total};
  }});
  window.addEventListener('pagehide',()=>lifecycle.abort(),{once:true});
}

// Preview cancellation styling immediately; the order changes only after Save order.
function syncOrderStatus(control) {
  const status = control.value;
  control.dataset.orderStatus = status;
  const save = control.closest('form')?.querySelector('[data-order-save]');
  if (save) save.dataset.orderStatus = status;
}
document.querySelectorAll('[data-order-status-control]').forEach(syncOrderStatus);
document.addEventListener('change', event => {
  if (event.target.matches('[data-order-status-control]')) syncOrderStatus(event.target);
});

// Accessible mobile navigation and compact, labeled admin table cards.
const mobileAdminButton=document.querySelector('[data-admin-menu]');
mobileAdminButton?.addEventListener('click',()=>{
  const expanded=mobileAdminButton.getAttribute('aria-expanded')!=='true';
  mobileAdminButton.setAttribute('aria-expanded',String(expanded));
  mobileAdminButton.closest('.admin-sidebar').classList.toggle('is-open',expanded);
});
function closeMobileMenus(){
  const menu=document.querySelector('[data-menu]');
  if(menu){menu.setAttribute('aria-expanded','false');document.getElementById('main-nav')?.classList.remove('open');}
  if(mobileAdminButton){mobileAdminButton.setAttribute('aria-expanded','false');mobileAdminButton.closest('.admin-sidebar').classList.remove('is-open');}
}
document.addEventListener('keydown',event=>{if(event.key==='Escape'){const menu=document.querySelector('[data-menu][aria-expanded="true"], [data-admin-menu][aria-expanded="true"]');closeMobileMenus();menu?.focus();}});
document.addEventListener('click',event=>{if(!event.target.closest('.site-header, .admin-sidebar'))closeMobileMenus();});
document.querySelectorAll('.main-nav a').forEach(link=>link.addEventListener('click',closeMobileMenus));
document.querySelectorAll('.admin-body .table-scroll table').forEach(table=>{
  const headings=Array.from(table.querySelectorAll('thead th'),heading=>heading.textContent.trim()||'Actions');
  if(!headings.length)return;
  table.setAttribute('role','table');
  table.querySelectorAll('tr').forEach(row=>row.setAttribute('role','row'));
  table.querySelectorAll('th').forEach(heading=>heading.setAttribute('role','columnheader'));
  table.querySelectorAll('tbody tr').forEach(row=>Array.from(row.cells).forEach((cell,index)=>{cell.dataset.label=headings[index]||'Details';cell.setAttribute('role','cell');}));
  table.classList.add('mobile-card-table');
  table.closest('.table-scroll').classList.add('mobile-table-container');
});

// Offers share the welcome dialog's design and one-at-a-time modal rule.
const offerDialog=document.querySelector('[data-offer-popup],[data-offer-preview]');
let offerReturnFocus=null;
function fillOfferDialog(o){
  offerDialog.querySelector('[data-offer-title]').textContent=o.title;
  offerDialog.querySelector('[data-offer-description]').textContent=o.description;
  offerDialog.querySelector('[data-offer-terms]').textContent=o.terms;
  const cta=offerDialog.querySelector('[data-offer-cta]');cta.textContent=o.cta_text||'Shop now';cta.href=o.cta_url||'/shop';
  const img=offerDialog.querySelector('[data-offer-image]');img.hidden=!o.image||o.image==='/assets/favicon.svg';img.onerror=()=>{img.hidden=true;};if(!img.hidden)img.src=o.image;
}
function openOfferDialog(){
  if(!offerDialog||document.querySelector('dialog[open]')||typeof offerDialog.showModal!=='function')return false;
  offerReturnFocus=document.activeElement;offerDialog.showModal();return true;
}
if(offerDialog){
  offerDialog.querySelectorAll('[data-close-offer]').forEach(b=>b.addEventListener('click',()=>offerDialog.close()));
  offerDialog.addEventListener('close',()=>offerReturnFocus?.focus());
  offerDialog.addEventListener('keydown',e=>{
    if(e.key!=='Tab')return;
    const c=Array.from(offerDialog.querySelectorAll('button,a[href]')).filter(n=>n.getClientRects().length);
    if(e.shiftKey&&document.activeElement===c[0]){e.preventDefault();c.at(-1).focus();}
    else if(!e.shiftKey&&document.activeElement===c.at(-1)){e.preventDefault();c[0].focus();}
  });
}
// Frequency preferences affect display only. Broken/blocked storage suppresses automatic popups.
function offerPopupSeen(o,at=Date.now()){
  try{const storage=o.frequency==='session'?sessionStorage:localStorage;const value=Number(storage.getItem('mscharm.offer.'+o.id)||0);return value>0&&(o.frequency!=='day'||at-value<86400000);}
  catch{return true;}
}
function rememberOfferPopup(o){try{(o.frequency==='session'?sessionStorage:localStorage).setItem('mscharm.offer.'+o.id,String(Date.now()));}catch{}}
async function showCurrentOffer(){
  let selected=null;
  try{
    const response=await fetch('/api/offers',{cache:'no-store',headers:{Accept:'application/json'}});
    if(response.ok){const list=await response.json();selected=list.find(o=>o.popup_enabled&&!offerPopupSeen(o));}
    if(selected){fillOfferDialog(selected);if(openOfferDialog()){rememberOfferPopup(selected);return;}}
  }catch{} // Network failures leave the shop usable; the offers page remains available.
  document.dispatchEvent(new Event('offers:empty'));
}
if(document.querySelector('[data-offer-popup]'))window.setTimeout(showCurrentOffer,700);
const offerForm=document.querySelector('[data-offer-form]');
if(offerForm){
  const field=name=>offerForm.elements.namedItem(name);
  const syncOfferForm=()=>{
    offerForm.querySelectorAll('[data-offer-scope]').forEach(panel=>panel.hidden=!panel.dataset.offerScope.split(',').includes(field('scope').value));
    const search=offerForm.querySelector('[data-product-search]').value.toLocaleLowerCase();
    const category=offerForm.querySelector('[data-product-category]').value;
    offerForm.querySelectorAll('[data-offer-product]').forEach(row=>row.hidden=!row.dataset.name.includes(search)||(category&&row.dataset.category!==category));
    const selected=Array.from(offerForm.querySelectorAll('input[name="'+(field('scope').value==='exclude'?'exclusions[]':'products[]')+'"]:checked'));
    offerForm.querySelector('[data-selected-products]').textContent=selected.length+' selected: '+selected.map(c=>c.closest('[data-offer-product]').querySelector('span').firstChild.textContent).join(', ');
    const value=field('discount_value');value.step=field('discount_type').value==='percentage'?'1':'0.01';value.min=field('discount_type').value==='percentage'?'1':'0.01';if(field('discount_type').value==='percentage')value.max='100';else value.removeAttribute('max');
    if(offerDialog?.open)previewOffer(false);
  };
  const previewOffer=(open=true)=>{
    let terms=(field('discount_type').value==='percentage'?field('discount_value').value+'% off':offerForm.dataset.currency+' '+field('discount_value').value+' off per piece')+' · '+field('scope').selectedOptions[0].textContent;
    if(Number(field('minimum_amount').value)>0)terms+=' · Eligible purchase of '+offerForm.dataset.currency+' '+field('minimum_amount').value+' minimum';
    if(Number(field('maximum_discount').value)>0)terms+=' · Savings capped at '+offerForm.dataset.currency+' '+field('maximum_discount').value+' per order';
    if(field('ends_at').value)terms+=' · Ends '+field('ends_at').value.replace('T',' ');
    terms+=' · Offers cannot be combined. We apply the better of promotional savings and your welcome discount. Sale prices do not stack.';
    fillOfferDialog({title:field('title').value||'Your promotional headline',description:field('description').value,terms,cta_text:field('cta_text').value,image:field('remove_image').checked?'':offerForm.dataset.previewImage||'',cta_url:'/shop'});
    if(open)openOfferDialog();
  };
  // Previews never navigate or submit the form.
  offerDialog.querySelector('[data-offer-cta]').addEventListener('click',e=>e.preventDefault());
  offerForm.addEventListener('input',syncOfferForm);offerForm.addEventListener('change',syncOfferForm);
  field('image').addEventListener('change',()=>{const file=field('image').files[0];if(!file)return;const reader=new FileReader();reader.onload=()=>{offerForm.dataset.previewImage=String(reader.result);if(offerDialog.open)previewOffer(false);};reader.readAsDataURL(file);});
  offerForm.querySelector('[data-preview-offer]').addEventListener('click',()=>previewOffer());syncOfferForm();
}
// Delivery selection uses server quotes; browser fees and totals are never authoritative.
const deliveryCheckout=document.querySelector('[data-delivery-checkout]');
if(deliveryCheckout){
  const areaControl=deliveryCheckout.querySelector('[data-delivery-area]');
  const summary=document.querySelector('[data-delivery-summary]');
  const feedback=deliveryCheckout.querySelector('[data-delivery-feedback]');
  const place=deliveryCheckout.querySelector('[data-place-order]');
  let deliveryRevision=0,deliveryBusy=false,deliveryAbort=null;
  async function refreshDelivery(){
    if(!areaControl)return;
    const revision=++deliveryRevision;
    deliveryAbort?.abort();deliveryAbort=new AbortController();deliveryBusy=true;place.disabled=true;
    feedback.textContent='Updating delivery and totals…';summary.setAttribute('aria-busy','true');
    const data=new URLSearchParams(new FormData(deliveryCheckout));data.set('delivery_revision',String(revision));
    try{
      const response=await fetch('/api/delivery',{method:'POST',body:data,signal:deliveryAbort.signal,headers:{Accept:'application/json'},cache:'no-store'});
      const result=await response.json();if(revision!==deliveryRevision)return;
      if(!response.ok||!result.ok)throw new Error(result.message||'Unable to update delivery.');
      // This fragment comes only from the application's escaped server-rendered totals view.
      summary.innerHTML=result.summary;
      feedback.textContent=result.ready?'Delivery fee and total updated. Please review before placing your order.':result.message;
      place.disabled=!result.ready||deliveryCheckout.dataset.storeOpen!=='1';
      const city=deliveryCheckout.querySelector('[data-delivery-city]');city.required=!!result.city_required;city.readOnly=!!result.city_locked;
      if(result.city_locked)city.value=result.city||'';
    }catch(error){if(error.name!=='AbortError'&&revision===deliveryRevision){feedback.textContent=error.message||'Delivery could not be updated. Use Review delivery fee or refresh checkout.';place.disabled=true;}}
    finally{if(revision===deliveryRevision){deliveryBusy=false;summary.removeAttribute('aria-busy');}}
  }
  if(areaControl){
    deliveryCheckout.addEventListener('change',event=>{if(event.target.matches('input:not([name="terms"]),textarea,select[name="delivery_area"]'))refreshDelivery();});
    deliveryCheckout.addEventListener('submit',event=>{
      if(event.submitter?.name==='review_delivery'){event.preventDefault();refreshDelivery();}
      else if(deliveryBusy||place.disabled){event.preventDefault();feedback.textContent='Please wait for a valid delivery quote and review the total.';}
    });
    const search=deliveryCheckout.querySelector('[data-delivery-search]');
    const governorate=deliveryCheckout.querySelector('[data-delivery-governorate]');
    const filterAreas=()=>{
      const term=search.value.trim().toLocaleLowerCase(),gov=governorate.value;let visible=0;
      for(const option of areaControl.options){if(!option.value||option.value==='other')continue;const matches=option.textContent.toLocaleLowerCase().includes(term)&&(!gov||option.dataset.governorate===gov);option.hidden=!matches&&!option.selected;if(matches)visible++;}
      deliveryCheckout.querySelector('[data-delivery-search-note]').textContent=visible?'Matching supported areas: '+visible+'. Your current selection is preserved.':'No matching supported area. Clear the search or contact the store.';
    };
    search.addEventListener('input',filterAreas);governorate.addEventListener('change',filterAreas);
  }
}
