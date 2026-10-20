'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const form=document.getElementById('stock-adjust-form'), modal=new bootstrap.Modal(document.getElementById('stock-adjust-modal')), productSelect=document.getElementById('adjust-product');
    const request = App.transactionRequest('stock-adjustments', document.getElementById('stock-request-status'));
    let products=[],saving=false,opening=false;
    const stocks=App.table('#stock-table',{ajax:'/ajax/products',order:[[0,'asc']],columns:[{data:'name',render:(v,t,r)=>`<strong>${App.escape(v)}</strong><small>${App.escape(r.sku)}</small>`},{data:'stock',render:(v,t,r)=>`<strong>${App.number(v)}</strong> ${App.escape(r.base_unit)}`},{data:null,orderable:false,render:r=>{const unit=[...(r.units||[])].filter(u=>u.multiplier>1).sort((a,b)=>b.multiplier-a.multiplier)[0];return unit?`${App.number(Math.floor(r.stock/unit.multiplier))} ${App.escape(unit.name)} + ${App.number(r.stock%unit.multiplier)} ${App.escape(r.base_unit)}`:'—';}},{data:'average_cost',render:App.money},{data:null,orderable:false,render:r=>`<span class="badge-soft-${r.stock===0?'danger':r.stock<=r.minimum_stock?'warning':'success'}">${r.stock===0?'Habis':r.stock<=r.minimum_stock?'Stok menipis':'Tersedia'}</span>`}]});
    const movements=App.table('#movement-table',{ajax:{url:'/ajax/movements',data:d=>{d.from=document.getElementById('movement-from').value;d.to=document.getElementById('movement-to').value;}},order:[[0,'desc']],columns:[{data:'date',render:App.date},{data:'product_name',orderable:false,render:App.escape},{data:'type',render:v=>`<span class="badge-soft-${v==='purchase'?'success':v==='sale'?'neutral':'warning'}">${({purchase:'Pembelian',sale:'Penjualan',adjustment:'Penyesuaian'})[v]||App.escape(v)}</span>`},{data:'quantity',render:(v,t,r)=>`<strong class="${v>0?'text-success':'text-danger'}">${v>0?'+':''}${App.number(v)}</strong> ${App.escape(r.base_unit)}`},{data:'balance',render:App.number},{data:'reference',render:(v,t,r)=>`${App.escape(v)}<small>${App.escape(r.notes||'')}</small>`}]});
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(el=>el.addEventListener('shown.bs.tab',()=>{stocks.columns.adjust();movements.columns.adjust();}));
    ['movement-from','movement-to'].forEach(id=>document.getElementById(id).addEventListener('change',()=>{const from=document.getElementById('movement-from').value,to=document.getElementById('movement-to').value;if(from&&to&&from>to){Swal.fire({icon:'info',title:'Periksa tanggal',text:'Tanggal akhir harus setelah tanggal awal.'});return;}movements.ajax.reload();}));
    async function summary(){try{const {data}=await App.http('GET','/ajax/summary',{from:App.today(),to:App.today()});document.getElementById('stock-product-count').textContent=App.number(data.product_count);document.getElementById('stock-value').textContent=App.money(data.stock_value);document.getElementById('stock-low-count').textContent=App.number(data.low_stock_count);}catch(_){}}
    document.getElementById('stock-adjust-open').addEventListener('click', async () => {
        if (saving || opening) return;
        if (request.pending) {
            await Swal.fire({ icon: 'info', title: 'Periksa penyesuaian sebelumnya', text: 'Gunakan tombol Periksa & lanjutkan sebelum mencatat perubahan stok lain.', confirmButtonText: 'Mengerti', confirmButtonColor: '#166534' });
            return;
        }
        opening = true;
        try {
            const { data } = await App.http('GET', '/ajax/lookups');
            products = data.products;
            productSelect.innerHTML = '<option value="">Pilih barang</option>' + products.map(p => `<option value="${p.id}">${App.escape(p.name)} — ${App.number(p.stock)} ${App.escape(p.base_unit)}</option>`).join('');
            form.reset(); App.formErrors(form);
            document.getElementById('adjust-date').value = App.today();
            updateDirection();
            document.getElementById('adjust-stock-info').textContent = 'Pilih barang untuk melihat stok saat ini.';
            modal.show();
        } catch (_) { /* App.http displays the error. */ }
        finally { opening = false; }
    });
    function updateDirection(){const addition=document.getElementById('adjust-direction').value==='add';document.getElementById('adjust-cost-wrap').hidden=!addition;document.getElementById('adjust-cost').required=addition;}
    document.getElementById('adjust-direction').addEventListener('change',updateDirection);
    productSelect.addEventListener('change',()=>{const p=products.find(p=>p.id===Number(productSelect.value));document.getElementById('adjust-stock-info').textContent=p?`Stok saat ini: ${App.number(p.stock)} ${p.base_unit}. Semua jumlah diisi dalam ${p.base_unit}.`:'Pilih barang.';document.getElementById('adjust-cost').value=p?Number(p.average_cost).toFixed(2):'';});
    document.getElementById('stock-adjust-modal').addEventListener('hide.bs.modal', event => { if (saving) event.preventDefault(); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (saving) return;
        const button = document.getElementById('adjust-submit');
        const payload = {
            product_id: Number(productSelect.value),
            quantity: Number(document.getElementById('adjust-quantity').value) * (document.getElementById('adjust-direction').value === 'add' ? 1 : -1),
            date: document.getElementById('adjust-date').value,
            notes: document.getElementById('adjust-notes').value,
            unit_cost: document.getElementById('adjust-direction').value === 'add' ? document.getElementById('adjust-cost').value : null,
        };
        saving = true;
        try {
            if (!await App.confirm('Simpan perubahan stok?', 'Stok akan berubah sesuai jumlah dan alasan yang Anda masukkan.')) return;
            App.busy(button, true);
            form.querySelectorAll('input,select,textarea,button').forEach(control => { control.disabled = true; });
            const result = await request.send('/ajax/stock-adjustments', payload, form);
            saving = false;
            modal.hide(); App.toast(result.message);
            stocks.ajax.reload(null, false); movements.ajax.reload(); summary();
        } catch (_) {
            if (request.pending) {
                const feedback = form.querySelector('.form-feedback');
                feedback.textContent = 'Hasil perubahan stok belum dapat dikonfirmasi. Tutup formulir, lalu pilih Periksa & lanjutkan sebelum membuat penyesuaian lain.';
                feedback.classList.remove('d-none');
            }
        } finally {
            saving = false;
            form.querySelectorAll('input,select,textarea,button').forEach(control => { control.disabled = false; });
            App.busy(button, false);
        }
    });
    summary();
});
