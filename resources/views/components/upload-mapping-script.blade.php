<style>
.mapping-control{min-width:450px;display:grid;gap:8px;text-align:left}.mapping-line{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.mapping-control select{max-width:270px;min-width:150px}.mapping-control input[type=number]{width:95px}.mapping-control .select-row{display:flex;align-items:center;gap:5px}.mapping-control input[type=checkbox]{min-height:0;width:16px;height:16px;padding:0;accent-color:#ea580c}.mapping-status{font-size:11px;color:#52525b}.mapping-control .save-mapping.is-saved{display:none}.brand-modal{position:fixed;inset:0;z-index:1200;background:#17171780;display:none;align-items:center;justify-content:center;padding:24px}.brand-modal.open{display:flex}.brand-modal .card{width:min(560px,100%);max-height:90vh;overflow:auto}.brand-modal .pad{display:grid;gap:16px}.brand-modal label{display:grid;gap:6px}.brand-modal input,.brand-modal select{width:100%}.review-modal .card{width:min(1200px,100%)}[hidden]{display:none!important}.btn:disabled{opacity:.5;cursor:wait}
</style>
<div class="brand-modal" id="brand-modal" role="dialog" aria-modal="true" aria-labelledby="brand-dialog-title" aria-hidden="true"><div class="card"><form class="pad" id="new-brand-form"><h2 id="brand-dialog-title">Add new brand</h2><p class="sub">Create a stock item, then save its mapping in this row.</p><label>Brand name<input id="new-brand-name" required maxlength="255"></label><label>Spirit type<select id="new-brand-type">@foreach(['Beer','Vodka','Whisky','Gin','Rum','Tequila','Wine','Liqueur','Alcopop','Brandy','Other'] as $type)<option>{{ $type }}</option>@endforeach</select></label><label>Bottle size (ml)<input id="new-brand-size" type="number" step="1" min="1" max="100000" required></label><label>Excise code (optional)<input id="new-brand-code" maxlength="60"></label><div class="actions"><button class="btn" id="save-new-brand">Create brand</button><button class="btn secondary" type="button" id="close-brand-modal">Cancel</button></div></form></div></div>
<div class="brand-modal review-modal" id="stock-review" role="dialog" aria-modal="true" aria-labelledby="review-title" aria-hidden="true"><div class="card"><div class="pad"><h2 id="review-title">Confirm stock submission</h2><p class="sub" id="review-note"></p></div><div class="table-wrap"><table><thead><tr><th>Brand</th><th>Before</th><th>Sold ml</th><th>Comp ml</th><th>NC ml</th><th>Change</th><th>After submission</th></tr></thead><tbody id="review-rows"></tbody></table></div><div class="pad"><div class="actions"><button class="btn" type="button" id="confirm-stock">Confirm & submit stock</button><button class="btn secondary" type="button" id="cancel-stock">Cancel</button></div></div></div></div>
<script>
(() => {
    const token=document.querySelector('input[name="_token"]')?.value;
    const documentId=@json($preview['document_id']);
    @php($brandData = $brands->map(fn($b)=>['id'=>$b->id,'name'=>$b->name,'type'=>$b->spirit_type,'size'=> (float)$b->bottle_size_ml])->values())
    const brands=@json($brandData);
    const source=@json(isset($preview['indent_number']) ? 'excise' : 'pos');
    const controls=[...document.querySelectorAll('.mapping-control')];
    const brandModal=document.getElementById('brand-modal'),reviewModal=document.getElementById('stock-review');
    let activeControl=null,pending=null;
    const updateSubmitState=()=>{
        const button=document.getElementById(source==='excise'?'submit-excise':'submit-stock');
        const ready=controls.length>0&&controls.some(c=>c.dataset.applied!=='1')&&controls.every(c=>c.dataset.applied==='1'||c.dataset.clean==='1');
        if(button)button.disabled=!ready;
    };
    const notifyError=error=>window.notify({type:'error',title:'Action not completed',message:error.message||'Please try again. No confirmation was received.'});
    const request=async(url,body)=>{
        const response=await fetch(url,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify(body)});
        let data;try{data=await response.json();}catch{throw new Error('The server returned an unreadable response. Check Upload history before retrying a stock submission.');}
        if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Request failed.');
        return data;
    };
    const modal=(element,open)=>{element.classList.toggle('open',open);element.setAttribute('aria-hidden',String(!open));if(open)element.querySelector('input,button')?.focus();};
    const state=(c,saved)=>{
        c.dataset.clean=saved?'1':'0';
        c.closest('tr').classList.toggle('mapping-ok',saved);
        c.closest('tr').classList.toggle('mapping-needs-attention',!saved);
        c.querySelector('.save-mapping').classList.toggle('is-saved',saved);
        c.querySelector('.mapping-status').textContent=c.dataset.applied==='1'?'Submitted — locked':saved?'Saved':'Not saved — review required';
        const count=document.getElementById('mapping-count');if(count)count.textContent=controls.filter(x=>x.dataset.clean==='1').length;
        updateSubmitState();
    };
    const render=(c,selected='')=>{
        const select=c.querySelector('.mapping-select');select.replaceChildren(new Option('＋ Add new brand…','__add__'),new Option('Not mapped',''));
        brands.filter(b=>b.type===c.querySelector('.mapping-type').value).forEach(b=>select.add(new Option(b.name+' · '+b.size+' ml',b.id)));
        select.value=String(selected||'');
    };
    const displayRules=c=>{
        const kind=c.querySelector('.mapping-kind')?.value||'bottle';
        c.querySelector('.mapping-select').hidden=['recipe','ignore'].includes(kind);
        c.querySelector('.mapping-type').hidden=['recipe','ignore'].includes(kind);
        for(const [selector,show] of [['.bottle-rule',kind==='bottle'],['.measured-rule',kind==='measured'],['.mapping-recipe',kind==='recipe']]){const node=c.querySelector(selector);if(node)node.hidden=!show;}
    };
    const ruleInput=c=>({kind:c.querySelector('.mapping-kind')?.value||'bottle',product_id:c.querySelector('.mapping-select').value?Number(c.querySelector('.mapping-select').value):null,recipe_id:Number(c.querySelector('.mapping-recipe')?.value)||null,serving_ml:c.querySelector('.mapping-ml')?.value||null,bottles_per_sale:Number(c.querySelector('.mapping-multiplier')?.value||1)});
    controls.forEach(c=>{
        const saved=JSON.parse(c.dataset.rules||'null'),brand=brands.find(b=>b.id===saved?.product_id);
        if(brand)c.querySelector('.mapping-type').value=brand.type;
        render(c,brand?.id);
        if(saved&&source==='pos'){
            c.querySelector('.mapping-kind').value=saved.kind;
            c.querySelector('.mapping-multiplier').value=saved.bottles_per_sale;
            c.querySelector('.mapping-ml').value=saved.serving_ml||'';
            c.querySelector('.mapping-recipe').value=saved.recipe_id||'';
        }
        displayRules(c);state(c,!!saved);
        if(c.dataset.applied==='1'){c.querySelectorAll('input,select,button').forEach(el=>el.disabled=true);return;}
        c.querySelector('.mapping-type').addEventListener('change',()=>{render(c);state(c,false);});
        c.querySelector('.mapping-select').addEventListener('change',()=>{
            state(c,false);
            if(c.querySelector('.mapping-select').value!=='__add__')return;
            activeControl=c;
            document.getElementById('new-brand-name').value=c.dataset.sourceName;
            document.getElementById('new-brand-type').value=c.querySelector('.mapping-type').value||'Other';
            document.getElementById('new-brand-size').value=c.dataset.bottleSize||'';
            document.getElementById('new-brand-code').value=c.dataset.exciseCode||'';
            c.querySelector('.mapping-select').value='';
            modal(brandModal,true);
        });
        c.querySelectorAll('.mapping-kind,.mapping-multiplier,.mapping-ml,.mapping-recipe').forEach(el=>el.addEventListener('change',()=>{state(c,false);displayRules(c);}));
        c.querySelector('.save-mapping').addEventListener('click',async event=>{
            const button=event.currentTarget,input=ruleInput(c),previous=JSON.parse(c.dataset.rules||'null');
            if(!input.kind||(input.kind==='bottle'||input.kind==='measured')&&!input.product_id)return notifyError(new Error('Choose a brand and explicit sale conversion.'));
            if(previous&&!confirm('Update the saved mapping for '+c.dataset.sourceName+'? Posted stock history will not change.'))return;
            button.disabled=true;
            try{
                const data=await request(@json(route('uploads.mapping')),{document_id:documentId,row_id:Number(c.dataset.rowId),...input,confirm_change:!!previous});
                c.dataset.rules=JSON.stringify(data.rules);state(c,true);window.notify({message:data.message});
            }catch(error){state(c,false);notifyError(error);}finally{button.disabled=false;}
        });
    });
    document.getElementById('auto-match')?.addEventListener('click',()=>{
        let count=0;
        const normalize=s=>s.toUpperCase().replace(/\(?\d+(?:\.\d+)?\s*ML\)?/g,' ').replace(/[^A-Z0-9]+/g,' ').trim();
        controls.filter(c=>c.dataset.applied!=='1'&&c.dataset.clean!=='1').forEach(c=>{
            const matches=brands.filter(b=>normalize(b.name)===normalize(c.dataset.sourceName)&&(!c.dataset.bottleSize||b.size===Number(c.dataset.bottleSize)));
            if(matches.length!==1)return;
            c.querySelector('.mapping-type').value=matches[0].type;render(c,matches[0].id);state(c,false);count++;
        });
        window.notify({message:count+' unique suggestions filled. Verify the brand and serving rule, then Save. Nothing was mapped automatically.'});
    });
    const submit=document.getElementById(source==='excise'?'submit-excise':'submit-stock');
    submit?.addEventListener('click',async()=>{
        const selected=controls.filter(c=>c.dataset.applied!=='1');
        if(!selected.length)return notifyError(new Error('There are no saved, unsubmitted rows.'));
        if(selected.some(c=>c.dataset.clean!=='1'))return notifyError(new Error('Save a valid mapping on every row first.'));
        submit.disabled=true;
        try{
            const rowIds=selected.map(c=>Number(c.dataset.rowId));
            const data=await request(@json(route('uploads.review')),{document_id:documentId,source,row_ids:rowIds});
            pending={document_id:documentId,row_ids:rowIds,review_key:data.review_key};
            document.getElementById('review-note').textContent=data.selected_rows+' rows selected; '+data.ignored+' explicitly ignored. Sold, complimentary and NC all consume stock. '+(data.period_notice||'');
            const body=document.getElementById('review-rows');body.replaceChildren();
            const number=n=>Number(n).toLocaleString(undefined,{maximumFractionDigits:2});
            const quantity=(ml,size)=>{const sign=ml<0?'-(':'',end=ml<0?')':'';ml=Math.round(Math.abs(ml)*100);size=Math.round(size*100);return sign+Math.floor(ml/size)+' bottles + '+number((ml%size)/100)+' ml'+end;};
            data.summary.forEach(r=>{const tr=document.createElement('tr');[r.name+' · '+r.size_ml+' ml',quantity(r.before_ml,r.size_ml),number(r.sold_ml),number(r.comp_ml),number(r.nc_ml),quantity(r.change_ml,r.size_ml),quantity(r.after_ml,r.size_ml)].forEach(value=>{const td=document.createElement('td');td.textContent=value;tr.append(td);});body.append(tr);});
            modal(reviewModal,true);
        }catch(error){notifyError(error);}finally{submit.disabled=false;}
    });
    document.getElementById('cancel-stock').addEventListener('click',()=>{pending=null;modal(reviewModal,false);});
    document.getElementById('confirm-stock').addEventListener('click',async event=>{
        if(!pending)return;
        const button=event.currentTarget;button.disabled=true;
        try{
            const data=await request(source==='excise'?@json(route('uploads.excise.apply')):@json(route('uploads.pos.apply')),pending);
            sessionStorage.setItem('pegwise-notice',data.message);
            window.location.assign(source==='excise'?@json(route('uploads.excise')):@json(route('uploads.pos')));
        }catch(error){pending=null;modal(reviewModal,false);notifyError(error);}finally{button.disabled=false;}
    });
    document.getElementById('close-brand-modal').addEventListener('click',()=>modal(brandModal,false));
    document.getElementById('new-brand-form').addEventListener('submit',async event=>{
        event.preventDefault();const button=document.getElementById('save-new-brand');button.disabled=true;
        try{
            const data=await request(@json(route('brands.store')),{name:document.getElementById('new-brand-name').value,spirit_type:document.getElementById('new-brand-type').value,bottle_size_ml:document.getElementById('new-brand-size').value,excise_code:document.getElementById('new-brand-code').value,opening_date:@json(now()->toDateString()),opening_bottles:0,opening_ml:0});
            const b=data.brand;brands.push({id:b.id,name:b.name,type:b.spirit_type,size:Number(b.bottle_size_ml)});
            controls.forEach(c=>{const type=c.querySelector('.mapping-type'),selected=c.querySelector('.mapping-select').value;if(![...type.options].some(o=>o.value===b.spirit_type))type.add(new Option(b.spirit_type,b.spirit_type));render(c,selected);});
            activeControl.querySelector('.mapping-type').value=b.spirit_type;render(activeControl,b.id);state(activeControl,false);
            modal(brandModal,false);window.notify({message:'Brand created and selected. Verify the serving rule and save this mapping.'});
        }catch(error){notifyError(error);}finally{button.disabled=false;}
    });
})();
</script>
