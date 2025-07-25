# Instalação do Drupal 9 com tema da FFLCH

## Requisitos

- *PHP 8.0* __(Somente versão 8.0 é compatível com todos os módulos instalados com o tema da FFLCH)__
- *Composer*
- *MySQL/MariaDB*
- *Apache*

## Passos para instalação

### Composer

1. Instale o Composer seguindo as instruções em [getcomposer.org](https://getcomposer.org/download/). Ou use o script abaixo.
Copie o seguinte script para um arquivo chamado `composer-setup.sh`, dê permissão de execução (`chmod +x composer-setup.sh`) e execute (`./composer-setup.sh`):

   ```bash
      #!/bin/bash
      EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
      php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
      ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

      if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
      then
      >&2 echo 'ERROR: Invalid installer checksum'
      rm composer-setup.php
      exit 1
      fi

      php composer-setup.php --quiet
      RESULT=$?
      rm composer-setup.php

      sudo mv composer.phar /usr/local/bin/composer # Se não tiver permissão, use ~/.local/bin

      exit $RESULT
   ```

### PHP 8.0

1. Adicione o repositório do PHP 8.0:

   ```bash
    sudo apt-get install software-properties-common
    sudo add-apt-repository ppa:ondrej/php
    sudo apt-get update
   ```

2. Instale o PHP 8.0 e as extensões necessárias:

   ```bash
   sudo apt-get install -y php8.0 libapache2-mod-php8.0 php8.0-cli php8.0-fpm php8.0-mbstring php8.0-xmlrpc php8.0-gd php8.0-xml \
   php8.0-intl php8.0-mysql php8.0-zip php8.0-curl php8.0-posix php8.0-dev php8.0-gmagick php8.0-gmp php8.0-dom php8.0-common php8.0-sybase php8.0-xdebug
   ```

3. Verifique a versão do PHP instalada (`php -v`). Para alterar a versão do PHP usada no terminal, é possível usar o comando `update-alternatives`:

   ```bash
    sudo update-alternatives --config php
   ```

4. Talvez seja necessário alterar o módulo do PHP no Apache para o PHP 8.0. Altere X.X para a versão usada.

   ```bash
    sudo a2enmod proxy_fcgi setenvif && sudo a2dismod phpX.X && sudo a2enmod php8.0 && sudo service apache2 restart
   ```

### Instalação do Drupal 9 com o tema da FFLCH

1. Clone o fork do repositório do tema FFLCH na branch `modulo-pragmatica`:

   ```bash
   git clone -b modulo-pragmatica https://github.com/paramosoftware/fflch-drupal.git fflch-drupal
   ```

2. Navegue até o diretório do Drupal:

   ```bash
    cd fflch-drupal
    ```

3. Instale as dependências do Drupal e do tema FFLCH usando o Composer.
A opção `--ignore-platform-reqs` é necessária para evitar erros de compatibilidade com módulos que não suportam totalmente PHP 8.0.

    ```bash
    composer install --ignore-platform-reqs
    ```

4. Crie um banco de dados MySQL/MariaDB para o Drupal (ex. `fflch_drupal`).

5. Instale o tema da FFLCH usando o Drush (ferramenta de linha de comando do Drupal). Altere o nome do banco de dados, usuário e senha conforme necessário (`mysql://[usuário]:[senha]@localhost/[banco_de_dados]`):

   ```bash
    ./vendor/bin/drush site-install fflchprofile \
      --db-url=mysql://user:password@localhost/drupal_fflch \
      --site-name="FFLCH" \
      --site-mail="admin@localhost" \
      --account-name="fflch" \
      --account-pass="admin" \
      --account-mail="admin@localhost" --yes
    ```

6. Alterar as permissões do diretório `web/sites` para permitir a criação do arquivo de configuração local:

    ```bash
    sudo chmod -R u+rw web/sites
    ```

7. Copie o arquivo de configuração do Drupal para o diretório `web/sites/default`,
para permitir configurações locais de desenvolvimento (mostrar erros, desabilitar cache, etc.):

   ```bash
   cp web/sites/example.settings.local.php web/sites/default/settings.local.php
   ```

8. Descomente as linhas 779 e 780 do arquivo `web/sites/default/settings.php` para usar o arquivo de configuração local:

   ```php
    if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
         include $app_root . '/' . $site_path . '/settings.local.php';
    }
   ```

9. O módulo `copycopyprevention` (`web/modules/contrib/copyprevention`) precisa ser manualmente alterado, para evitar erros de compatibilidade com o PHP 8.0.

   Altere as linhas 40, 72 e 73 do arquivo `web/modules/contrib/copyprevention/copyprevention.module` de:

   ```php
     $body_settings = array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_body'));
     'body' => array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_body')),
     'images' => array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_images')),
   ```

    para:

    ```php
      $body_settings = array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_body') ?? []);
      'body' => array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_body') ?? []),
      'images' => array_filter(\Drupal::configFactory()->getEditable('copyprevention.settings')->get('copyprevention_images') ?? []),
    ```

10. A instalação pode ser acessada usando o endereço [http://localhost/fflch-drupal/web](http://localhost/fflch-drupal/web) (ou o caminho usado).

11. Faça login com o usuário `fflch` e a senha `admin` em [http://localhost/fflch-drupal/web/user/login](http://localhost/fflch-drupal/web/user/login).
