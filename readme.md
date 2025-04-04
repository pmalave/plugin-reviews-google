# Avanza Google Places Reviews

Plugin de WordPress personalizado para mostrar reseñas de Google Places en las tiendas de Avanza Fibra.

## Descripción

Este plugin permite mostrar las reseñas de Google Places en las páginas de tiendas de Avanza Fibra. Características principales:

- Muestra la valoración media con estrellas
- Visualiza las 3 reseñas más recientes
- Diseño responsive y personalizado con los colores corporativos
- Caché de 6 horas para optimizar las llamadas a la API
- Soporte para múltiples tiendas con diferentes Place IDs

## Instalación

1. Sube la carpeta `avanza-google-places-reviews` al directorio `/wp-content/plugins/`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. Ve a 'Ajustes' > 'Google Reviews' para configurar tu API Key de Google Places
4. Añade el ID de Google Places en el campo personalizado `id_google_maps` de cada tienda

## Uso

1. Obtén una API Key de Google Places desde la [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Configura la API Key en el panel de administración del plugin
3. Añade el siguiente shortcode donde quieras mostrar las reseñas:
