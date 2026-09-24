<div class="panel" id="emc-preview-panel" data-preview-url="{$emc_preview_url|escape:'htmlall':'UTF-8'}">
  <div class="panel-heading"><i class="icon-eye"></i> Previsualización de emails</div>
  <p>
    La previsualización renderiza el <strong>HTML real del email</strong> y aplica las reglas activas <strong>sin escribir ni modificar la plantilla</strong>. Las variables dinámicas usan datos ficticios seguros para no romper enlaces, imágenes ni atributos HTML.
  </p>
  <div class="row">
    <div class="col-lg-5">
      <label>Email / plantilla</label>
      <select id="emc-template" class="form-control">
        {foreach from=$emc_templates item=t}
          <option value="{$t.template|escape:'htmlall':'UTF-8'}">{$t.template|escape:'htmlall':'UTF-8'}</option>
        {/foreach}
      </select>
    </div>
    <div class="col-lg-3">
      <label>Idioma</label>
      <select id="emc-lang" class="form-control">
        {foreach from=$emc_languages item=l}
          <option value="{$l.id_lang|intval}">{$l.name|escape:'htmlall':'UTF-8'} ({$l.iso_code|escape:'htmlall':'UTF-8'})</option>
        {/foreach}
      </select>
    </div>
    <div class="col-lg-2" style="padding-top:25px">
      <button type="button" id="emc-preview-btn" class="btn btn-primary"><i class="icon-eye"></i> Ver previo</button>
    </div>
  </div>
  <p id="emc-file" class="help-block" style="margin-top:10px"></p>
  <iframe id="emc-preview-frame" sandbox="" style="width:100%;height:720px;border:1px solid #bbcdd2;background:white"></iframe>
</div>
<script>
{literal}
(function () {
  var btn = document.getElementById('emc-preview-btn');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var url = document.getElementById('emc-preview-panel').getAttribute('data-preview-url')
      + '&template=' + encodeURIComponent(document.getElementById('emc-template').value)
      + '&id_lang=' + encodeURIComponent(document.getElementById('emc-lang').value);
    url += '&_ts=' + Date.now();
    fetch(url, {credentials:'same-origin', cache:'no-store'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.ok) { alert(data.error || 'Error'); return; }
        var version = data.preview_version || 'ANTIGUA';
        document.getElementById('emc-file').textContent =
          'Motor de previo: v' + version + ' · Origen: ' + data.file +
          (data.unresolved_critical ? ' · AVISO: quedan variables críticas sin resolver' : '');
        document.getElementById('emc-preview-frame').srcdoc = data.html;
      })
      .catch(function(e){ alert('Error cargando previo: ' + e); });
  });
})();
{/literal}
</script>
