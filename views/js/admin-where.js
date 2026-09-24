document.addEventListener('DOMContentLoaded', function () {
  var position = document.querySelector('[name="position"]');
  if (!position || document.getElementById('emc-open-where')) return;

  var group = position.closest ? position.closest('.form-group') : null;
  if (!group || !group.parentNode) return;

  var row = document.createElement('div');
  row.className = 'form-group';
  row.innerHTML =
    '<label class="control-label col-lg-3"></label>' +
    '<div class="col-lg-9">' +
      '<button type="button" class="btn btn-default" id="emc-open-where">' +
        '<i class="icon-eye"></i> ¿Dónde? Ver zonas del email' +
      '</button>' +
      '<p class="help-block">Mapa visual de las zonas prefijadas. Haz clic en una zona para elegirla.</p>' +
    '</div>';
  group.parentNode.insertBefore(row, group.nextSibling);

  var modal = document.createElement('div');
  modal.id = 'emc-where-modal';
  modal.style.cssText = 'display:none;position:fixed;z-index:100000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.58);overflow:auto';
  modal.innerHTML =
   '<div style="max-width:1050px;margin:30px auto;background:#fff;border-radius:6px;box-shadow:0 10px 35px rgba(0,0,0,.35)">' +
    '<div style="padding:14px 18px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center">' +
      '<div><b style="font-size:18px">Mapa de zonas del email</b><div style="color:#777">Haz clic en una zona.</div></div>' +
      '<button type="button" class="btn btn-default" id="emc-close-where">Cerrar ×</button>' +
    '</div>' +
    '<div style="padding:20px;background:#eee">' +
      '<div style="max-width:680px;margin:auto;background:#fff;border:1px solid #aaa">' +
        zone('after_header','LOGO / CABECERA','Después de la cabecera / logo') +
        '<div style="padding:22px"><h3>Hola Nombre Apellidos</h3><p>Gracias por comprar en Mi tienda.</p>' +
          zone('before_order','ANTES DE DETALLES DEL PEDIDO','Insertar antes') +
          zone('after_order','DETALLES DEL PEDIDO','Insertar después') +
          zone('before_ship','ANTES DE TRANSPORTE','Insertar antes') +
          zone('after_ship','TRANSPORTE','Insertar después') +
          zone('before_addr','ANTES DE DIRECCIONES','Insertar antes') +
          zone('after_addr','DIRECCIONES DE ENTREGA Y FACTURACIÓN','Insertar después') +
          zone('before_guest','SEGUIMIENTO DE INVITADO','Insertar antes') +
        '</div>' +
        zone('before_footer','PIE DEL EMAIL','Insertar antes del pie') +
      '</div>' +
    '</div>' +
   '</div>';
  document.body.appendChild(modal);

  function zone(value, title, sub) {
    return '<div class="emc-zone" data-pos="'+value+'" style="cursor:pointer;padding:16px;margin:10px;border:2px dashed #7f9fa6">' +
      '<b>'+title+'</b><br><small>'+sub+'</small></div>';
  }

  document.getElementById('emc-open-where').onclick = function () { modal.style.display = 'block'; };
  document.getElementById('emc-close-where').onclick = function () { modal.style.display = 'none'; };
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

  var zones = modal.querySelectorAll('.emc-zone');
  for (var i=0; i<zones.length; i++) {
    zones[i].onmouseenter = function(){ this.style.background='#fff3cd'; };
    zones[i].onmouseleave = function(){ this.style.background=''; };
    zones[i].onclick = function () {
      position.value = this.getAttribute('data-pos');
      var ev = document.createEvent('HTMLEvents');
      ev.initEvent('change', true, false);
      position.dispatchEvent(ev);
      modal.style.display = 'none';
    };
  }
});
