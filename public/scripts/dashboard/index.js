'use strict';
document.addEventListener('DOMContentLoaded', () => {
    const period = document.getElementById('dashboard-period');
    const range = () => { const to = App.today(); const date = new Date(to + 'T12:00:00'); if (period.value === 'week') date.setDate(date.getDate()-6); if (period.value === 'month') date.setDate(1); return {from: date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0'), to}; };
    const status = value => `<span class="badge-soft-${value === 'paid' ? 'success' : 'warning'}">${value === 'paid' ? 'Lunas' : value === 'partial' ? 'Sebagian' : 'Belum dibayar'}</span>`;
    const table = App.table('#recent-sales',{ajax:{url:'/ajax/sales',data:d=>Object.assign(d,range())},pageLength:5,lengthChange:false,searching:false,order:[[0,'desc']],columns:[{data:'date',render:(v,t,row)=>'<strong>'+App.escape(row.number)+'</strong><small>'+App.date(v)+'</small>'},{data:'customer_name',render:v=>App.escape(v || 'Pelanggan umum')},{data:'total',render:v=>App.money(v)},{data:'status',orderable:false,render:status}]});
    function chart(rows) {
        const values = rows.length ? rows : [{date:App.today(),total:0}];
        const max = Math.max(...values.map(r=>Number(r.total)),1); const w=640,h=174,left=45,top=13,bottom=153;
        const points = values.map((r,i)=>[left + (values.length===1?0:i/(values.length-1))*(w-left-12),bottom-Number(r.total)/max*(bottom-top)]);
        if(points.length===1) points.push([w-12,points[0][1]]);
        const line=points.map(p=>p.join(',')).join(' '); const area=`${left},${bottom} ${line} ${w-12},${bottom}`;
        const grid=[0,.5,1].map(n=>{const y=bottom-(bottom-top)*n;const val=max===1?0:max*n;const label=val>=1000000?(val/1000000).toFixed(1)+'jt':val>=1000?(val/1000).toFixed(0)+'rb':App.number(val);return `<line x1="${left}" y1="${y}" x2="${w-12}" y2="${y}" stroke="#edf1ed" stroke-dasharray="4 5"/><text x="1" y="${y+3}" fill="#a5b0a7" font-size="9">${label}</text>`}).join('');
        document.getElementById('sales-chart').innerHTML=`<svg class="sales-chart" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7eb28e" stop-opacity=".22"/><stop offset="100%" stop-color="#7eb28e" stop-opacity=".02"/></linearGradient></defs>${grid}<polygon points="${area}" fill="url(#chart-fill)"/><polyline points="${line}" fill="none" stroke="#58966e" stroke-width="2.5" stroke-linejoin="round"/>${values.length<12?points.map(p=>`<circle cx="${p[0]}" cy="${p[1]}" r="3" fill="#58966e" stroke="white" stroke-width="2"/>`).join(''):''}</svg>`;
        const indexes=[...new Set([0,Math.floor((values.length-1)/2),values.length-1])];
        document.getElementById('chart-axis').innerHTML=indexes.map(i=>'<span>'+App.date(values[i].date)+'</span>').join('');
        document.getElementById('sales-chart').setAttribute('aria-label','Penjualan per hari: '+values.map(r=>App.date(r.date)+' '+App.money(r.total)).join('; '));
    }
    let latest=0;
    async function load() { const ticket=++latest; try { const {data} = await App.http('GET','/ajax/summary',range()); if(ticket!==latest)return; document.querySelectorAll('[data-metric]').forEach(el=>el.textContent=App.money(data[el.dataset.metric])); document.querySelectorAll('[data-count]').forEach(el=>el.textContent=App.number(data[el.dataset.count])); document.getElementById('getting-started').hidden=Number(data.product_count)>0; chart(data.daily||[]); document.getElementById('low-stock-list').innerHTML=(data.low_stock||[]).slice(0,4).map(p=>`<div class="stock-alert-row"><span class="product-avatar"><i class="bi bi-box-seam"></i></span><div><strong>${App.escape(p.name)}</strong><small>${App.escape(p.sku)}</small></div><span class="badge-soft-${p.stock>0?'warning':'danger'}">${App.number(p.stock)} ${App.escape(p.base_unit)}</span></div>`).join('') || '<div class="empty-state"><i class="bi bi-check2-circle"></i><strong>Belum ada stok yang menipis</strong><span>Barang dengan stok rendah akan muncul di sini.</span></div>'; }catch(_){} }
    period.addEventListener('change',()=>{load();table.ajax.reload();});load();
});
