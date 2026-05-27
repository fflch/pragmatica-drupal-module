# Pragmática - Módulo para Drupal 9

Módulo para Drupal 9 para importar, gerenciar e exibir dados de análises linguísticas resultantes pesquisa realizada pelo
[Grupo de Pesquisa - Pragmática (inter)linguística, intercultural e cross-cultural (GPP)](https://www.gppragmatica-usp.com/).
O módulo foi desenvolvido pela [Páramo Software](https://www.paramosoftware.com.br/) para ser usado com a instalação do [Drupal da FFLCH-USP](https://github.com/fflch/drupal).


## Instalação

Para instalação do Drupal 9 com o tema da FFLCH, siga as instruções em [docs/instalacao-drupal-fflch.md](docs/instalacao-drupal-fflch.md).

Para o funcionamento do módulo Pragmática, foram necessárias duas alterações no código fonte da instalação do Drupal da FFLCH, listadas abaixo:

1. **Atualização do Drush para a versão 10:**

Para compatibilidade, é necessário usar a versão 10 ou superior do Drush, que é a mínima compatível com o Drupal 9. No `composer.json` do Drupal, alterou-se a versão do Drush de `^8.0` para `^10.0`:

```json
"require": {
    ...
    "drush/drush": "^10.0",
    ...
}
```

2. **Correção de constante REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL**:

A constante `REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL` foi alterada para utilizar o namespace `Drupal\user\UserInterface` no arquivo `web/profiles/contrib/fflchprofile/fflchprofile.install`, linha 23. A alteração é a seguinte: 

```php
...
// adicionar novo namespace
use Drupal\user\UserInterface;
...

// Antes 
$user_settings->set('register', USER_REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save(FALSE);
// Depois
$user_settings->set('register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save(FALSE);
```

Para alterações exatas, ver commit `665b0571b320dc8518bab00bb957c7b138b93e51` na branch `modulo-pragmatica` do [fork do repositório drupal da FFLCH](https://github.com/paramosoftware/fflch-drupal).


Depois de instalar o Drupal e aplicar as alterações acima, siga as instruções abaixo para instalar o módulo Pragmática:

1. Na raiz projeto do Drupal, clone o repositório do módulo Pragmática:

   ```bash
   git clone https://github.com/paramosoftware/pragmatica-drupal-module.git web/modules/custom/pragmatica
   ```
2. Importante: verifique qual é a branch mais recente e faça checkout pra evitar instalar uma versão desatualizada do módulo
    
3. Instale o módulo usando Drush:
   ```bash
   ./vendor/bin/drush en pragmatica -y
   ```


## Comandos do Drush

O executável do Drush deve estar instalado em `./vendor/bin/drush`. Para evitar problema com permissões, é recomendado
executar os comandos do Drush como o usuário do servidor web, como a seguir:

```bash
sudo -u www-data ./vendor/bin/drush <comando>
```

- **Limpeza de cache:** `drush cr` ou `drush cache:rebuild`
- **Atualização do banco de dados:** `drush updb` ou `drush updatedb`
- **Verificar atualizações nas Entities:** `drush updbst` ou `drush updatedb:status`
- **Instalar módulo:** `drush en nome_do_modulo -y`
- **Desinstalar módulo:** `drush pmu nome_do_modulo`


## Organização do código

O código do módulo Pragmática está organizado em pastas, seguindo a estrutura de entidades e campos do Drupal:

```
pragmatica/
├── src/
│   ├── Controller/          # Controladores para rotas e páginas do módulo
│   ├── Entity/              # Definições de entidades personalizadas
│   ├── Form/                # Formulários para criação e edição de entidades
│   ├── Importer/            # Classes para importação de dados
│   ├── ListBuilder/         # Classes para construção de listas de entidades
```

Para criar uma nova entidade, o mínimo necessário é criar uma classe que estenda a classe `PragmaticaBaseEntity` e
implemente os métodos necessários. A classe deve ser colocada na pasta `src/Entity/`. Depois, é necessário registrar as
rotas no arquivo `pragmatica.routing.yml` e item no menu no arquivo `pragmatica.links.menu.yml`.

## Links úteis

[Defining and using Content Entity Field definitions](https://www.drupal.org/docs/drupal-apis/entity-api/defining-and-using-content-entity-field-definitions) - Resumo dos usos dos diferentes tipos de campos e configurações para as entidades de conteúdo.
[FieldTypes, FieldWidgets and FieldFormatters](https://www.drupal.org/docs/drupal-apis/entity-api/fieldtypes-fieldwidgets-and-fieldformatters) - Resumo dos tipos de campos, widgets e formatadores disponíveis no Drupal.
https://git.drupalcode.org/project/examples - Repositório com exemplos das principais funcionalidades do Drupal.

## Problemas conhecidos

## Erros no módulo ```copyprevention```

Ao acessar uma página pública, deslogado, o módulo `copyprevention` gera erros, como o seguinte:

```
TypeError: array_filter(): Argument #1 ($array) must be of type array, null given in array_filter() (line 72 of modules/contrib/copyprevention/copyprevention.module).
```

Para solucionar o problema, é necessário desativar o módulo `copyprevention` com o comando:

```bash
sudo -u www-data ./vendor/bin/drush pmu copyprevention
```

## Atualização do banco de dados
Por alguma razão, o schema do banco de dados não é atualizado automaticamente ao executar `drush updb`, apesar de alterações
serem listadas com comando `drush updbst`.

Normalmente, desinstalar e reinstalar o módulo Pragmática atualiza o schema do banco de dados, porém caso haja uma mudança
ainda não executada, ao tentar desinstalar o módulo ocorrerá um erro de tabela inexistente, como o seguinte:
`SQLSTATE[42S02]: Base table or view not found: 1146 Table 'pragmatica_code' doesn't exist`

Nesses casos, para atualizar o schema é necessário apagar as atualizações registradas na tabela
`key_value` com a query:

```sql
DELETE FROM key_value WHERE name like 'pragmatica%';
```

A query apaga todas as atualizações registradas para o módulo Pragmática. É possível ser mais específico e apagar apenas
as atualizações de uma unica `Entity`, com `name like '<entity_type>%'`, onde `<entity_type>` é o tipo de entidade,
por exemplo, `pragmatica_code`.

Depois disso, é necessário registrar manualmente as atualizações do banco de dados com o comando:

```php
sudo -u www-data ./vendor/bin/drush php:eval '
                              $module = "pragmatica";

                              $entity_manager = \Drupal::service("entity_type.manager");
                              $definition_update_manager = \Drupal::service("entity.definition_update_manager");
                              $definitions = $entity_manager->getDefinitions();

                              foreach ($definitions as $entity_type_id => $definition) {
                                  if ($definition->getProvider() === $module) {
                                      echo "Installing: $entity_type_id\n";
                                      try {
                                          $definition_update_manager->installEntityType($definition);
                                      } catch (\Exception $e) {
                                          echo "Error to install $entity_type_id: " . $e->getMessage() . "\n";
                                      }
                                  }
                              }
                              '
```
