<style>
    .catalogue-form { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
    .catalogue-form label { display:grid; gap:8px; }
    .catalogue-form .note,.recipe-editor { grid-column:1 / -1; }
    .ingredient-row { display:flex; gap:12px; align-items:end; margin:12px 0; flex-wrap:wrap; }
    .ingredient-row label { flex:1; min-width:150px; }
    .brand-table { table-layout:fixed; }
    .brand-table .brand-name-column { width:34%; }
    .brand-table .brand-type-column { width:16%; }
    .brand-table .brand-size-column { width:16%; }
    .brand-table .brand-code-column { width:18%; }
    .brand-table .brand-actions-column { width:16%; }
    .brand-table th:nth-child(2), .brand-table td:nth-child(2), .brand-table th:nth-child(3), .brand-table td:nth-child(3), .brand-table th:nth-child(4), .brand-table td:nth-child(4) { text-align:center; }
    select { max-width:100%; }
</style>
<script>
function filterCatalogue(query) {
    document.querySelectorAll('[data-catalogue-row]').forEach(row => row.hidden = !row.textContent.toLowerCase().includes(query.toLowerCase()));
}
</script>
