# Ecom Mail Content

Módulo para PrestaShop que permite **añadir o eliminar contenido de los emails sin modificar las plantillas de correo**.

## Versión
**1.0.5**

Compatible con PrestaShop **8.1 o superior**.

## Características
- Añadir contenido HTML y texto plano.
- Eliminar elementos mediante selector CSS.
- Eliminar texto o variables concretas.
- Configuración multiidioma.
- Aplicar reglas a una plantilla, varias plantillas o **TODOS LOS EMAILS**.
- Ordenar y activar/desactivar reglas.
- Vista previa de emails.
- No modifica los archivos originales `.html`, `.txt`, `.tpl` o `.twig`.
- Utiliza hooks de PrestaShop durante la generación y envío del correo.

## Posiciones predefinidas
- Después de la cabecera / logo.
- Antes de detalles del pedido.
- Después de detalles del pedido.
- Antes del bloque de transporte.
- Después del bloque de transporte.
- Antes de las direcciones.
- Después de las direcciones.
- Antes de seguimiento de invitado.
- Después de seguimiento de invitado.
- Antes del pie del email.
- Inicio del email.
- Final del email.

### Posiciones mediante selector CSS
- Antes del selector.
- Después del selector.
- Dentro del selector, al inicio.
- Dentro del selector, al final.

## Funcionamiento
Las reglas se aplican al contenido del email durante el proceso de generación/envío. El módulo no necesita modificar físicamente las plantillas.

Las posiciones predefinidas utilizan referencias semánticas del correo —detalles del pedido, transporte, direcciones, seguimiento y pie— para trabajar mejor con la estructura de los emails Modern de PrestaShop.

## Añadir contenido
Una regla de **Añadir contenido** permite configurar:
- Plantilla o plantillas.
- Posición.
- Contenido HTML por idioma.
- Contenido de texto plano por idioma.
- Orden.
- Estado activo/inactivo.

Si el HTML está vacío y existe texto plano, el módulo puede utilizar ese texto también en el email HTML.

## Eliminar contenido
Una regla de **Quitar elemento** puede utilizar:
- Selector CSS.
- Texto o variable concreta a eliminar.

## Instalación
1. Descarga `ecommailcontent-1.0.5.zip`.
2. Ve a **Módulos > Gestor de módulos**.
3. Pulsa **Subir un módulo**.
4. Selecciona el ZIP.
5. Instálalo.
6. Abre la configuración y crea las reglas.

## Ejemplo
Para añadir un aviso después de las direcciones de `order_conf`:

- **Email / plantilla:** `order_conf`
- **Acción:** Añadir contenido
- **Dónde:** Después de las direcciones
- **Contenido HTML:**

```html
<p><strong>Información adicional sobre su pedido.</strong></p>
```

## Estructura
```text
ecommailcontent/
├── classes/
├── controllers/
│   └── admin/
├── views/
│   ├── js/
│   └── templates/
├── ecommailcontent.php
└── README.md
```

## Recomendaciones
Antes de utilizar una regla en producción:
1. Comprueba la vista previa.
2. Realiza un envío de prueba.
3. Verifica HTML y texto plano.
4. Comprueba distintos clientes de correo si añades HTML complejo.

## Autor
**ecom y seo - Gustavo Martos**
