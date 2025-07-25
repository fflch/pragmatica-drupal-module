# Pragmática - Módulo para Drupal 9

Módulo para Drupal 9 para importar, gerenciar e exibir dados de análises linguísticas resultantes pesquisa realizada pelo 
[Grupo de Pesquisa - Pragmática (inter)linguística, intercultural e cross-cultural (GPP)](https://www.gppragmatica-usp.com/). 
O módulo é desenvolvido especificamente para ser usado com a instalação do [Drupal da FFLCH-USP](https://github.com/fflch/drupal).

## Instalação

Para instalação do Drupal 9 com o tema da FFLCH, siga as instruções em [docs/instalacao-drupal-fflch.md](docs/instalacao-drupal-fflch.md).

Depois de instalar o Drupal, siga as instruções abaixo para instalar o módulo Pragmática:

1. Na raiz projeto do Drupal, clone o repositório do módulo Pragmática:

   ```bash
   git clone https://github.com/paramosoftware/pragmatica-drupal-module.git web/modules/custom/pragmatica
   ```
   
2. Instale o módulo usando Drush:
   ```bash
   ./vendor/bin/drush en pragmatica -y
   ```