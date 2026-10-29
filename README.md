# handersonsilva/laravel-bruno-docs

Lê uma coleção [Bruno](https://www.usebruno.com/) e entrega uma página Swagger UI com o OpenAPI 3 gerado em tempo de execução. Os arquivos `.bru` continuam sendo a fonte. O pacote não os altera.

Requisitos: PHP 8.4 e Laravel 13.

## Instalação

```bash
composer require handersonsilva/laravel-bruno-docs
```

O provider `Dotlib\BrunoDocs\BrunoDocsServiceProvider` é descoberto pelo Laravel. Não é preciso registrá-lo em `bootstrap/providers.php`.

## Publicar

Dois tags independentes:

```bash
php artisan vendor:publish --tag=bruno-docs-config
php artisan vendor:publish --tag=bruno-docs-assets --force
```

| Tag | Destino |
| --- | --- |
| `bruno-docs-config` | `config/bruno-docs.php` |
| `bruno-docs-assets` | `public/vendor/bruno-docs/swagger-ui.css` e `swagger-ui-bundle.js` |

O CSS e o JS precisam estar em `public/`. Servidores que entregam `*.css` e `*.js` como arquivo estático não encaminham o pedido ao PHP quando o arquivo não existe. Sem o publish dos assets, a página abre e o Swagger fica sem estilo e sem script.

O config publicado é opcional. Sem ele, vale o config do pacote e `collection` fica vazio até a aplicação definir o caminho.

## Configuração

Chaves de `config/bruno-docs.php`:

| Chave | Env | Padrão | Efeito |
| --- | --- | --- | --- |
| `enabled` | `BRUNO_DOCS_ENABLED` | `true` | `false` não registra as rotas. Se a config mudar no mesmo processo, a requisição responde 404. |
| `path` | `BRUNO_DOCS_PATH` | `docs` | Prefixo da URL, sem barras nas pontas. Vazio volta para `docs`. |
| `access` | `BRUNO_DOCS_ACCESS` | `private` | Só `public` abre sem sessão. Qualquer outro valor fica privado. |
| `guard` | `BRUNO_DOCS_GUARD` | `web` | Guard do `auth` no modo private. Vazio volta para `web`. |
| `middleware.public` | — | `['web']` | Grupo quando `access` é `public`. |
| `middleware.private` | — | `['web', 'auth', 'verified']` | Grupo quando `access` é `private`. |
| `collection` | `BRUNO_DOCS_COLLECTION` | `null` | Pasta absoluta da coleção, a que contém `bruno.json`. |
| `title` | `BRUNO_DOCS_TITLE` | `null` | Título do spec. Vazio usa o nome do `bruno.json`. |
| `cache` | `BRUNO_DOCS_CACHE` | `true` | Guarda o spec até a data de modificação dos `.bru` mudar. |
| `allowed_emails` | `BRUNO_DOCS_ALLOWED_EMAILS` | vazio | E-mails separados por `\|`. Só vale com `access=private`. |

`enabled` e `cache` aceitam os booleanos do `env()` do Laravel. A string `"false"` também desliga a opção.

Exemplo no `.env`:

```dotenv
BRUNO_DOCS_ENABLED=true
BRUNO_DOCS_PATH=docs
BRUNO_DOCS_ACCESS=private
BRUNO_DOCS_GUARD=web
BRUNO_DOCS_ALLOWED_EMAILS=test@test.com|test2@test.com
BRUNO_DOCS_COLLECTION=/caminho/absoluto/api-docs
BRUNO_DOCS_TITLE=
BRUNO_DOCS_CACHE=true
```

Publique o config e aponte `collection` para a pasta que contém `bruno.json`:

```php
'guard' => env('BRUNO_DOCS_GUARD', 'web'),

'collection' => env('BRUNO_DOCS_COLLECTION', base_path('../api-docs')),

'allowed_emails' => env('BRUNO_DOCS_ALLOWED_EMAILS', ''),
```

O prefixo não deve ficar sob um grupo que exige token de API ou que responde só JSON.

## Rotas

Com `BRUNO_DOCS_PATH=docs`:

| Método | URL | Nome | Conteúdo |
| --- | --- | --- | --- |
| `GET` | `/docs` | `bruno-docs.show` | Página Swagger UI |
| `GET` | `/docs/openapi.json` | `bruno-docs.spec` | OpenAPI 3.0.3 |

As duas usam o grupo `web` e, por cima, as regras de acesso abaixo. `web` dentro de `middleware.public` ou `middleware.private` não é aplicado de novo.

O botão Authorize do Swagger vale para o "Try it out" contra a API. O spec declara o scheme `bearerAuth` e não embute `api_token`.

## Acesso

`BRUNO_DOCS_ACCESS=public` abre a página e o spec sem sessão. Nesse modo `BRUNO_DOCS_GUARD` e `BRUNO_DOCS_ALLOWED_EMAILS` não são lidos.

`BRUNO_DOCS_ACCESS=private` é o padrão. Qualquer outro valor também fica privado. A ordem é esta:

1. O visitante autentica no guard de `BRUNO_DOCS_GUARD`. O padrão é `web`. Vazio também usa `web`. A middleware resultante é `auth:web` (ou `auth:` mais o nome configurado) e, em seguida, `verified`.
2. Sem sessão nesse guard, o pedido vai para a tela de login. Uma sessão de outro guard não serve. O token Bearer da API também não abre a página: ele só vale no "Try it out".
3. Se `BRUNO_DOCS_ALLOWED_EMAILS` estiver vazio, qualquer usuário autenticado nesse guard entra.
4. Se a variável tiver endereços, o e-mail da sessão precisa estar na lista. O formato é `test@test.com|test2@test.com|test3@test.com`. A comparação ignora maiúsculas e espaços em volta de cada item. Itens vazios entre os `|` são descartados. Quem está logado fora da lista recebe 403. A mensagem em inglês é "You are not allowed to view the API documentation." A tradução fica no pacote, em `lang/pt_BR.json`.

`middleware.private` continua configurável. Um item `auth` ou `auth:outro` nessa lista é reescrito para o guard de `BRUNO_DOCS_GUARD`. Se a lista privada não tiver `auth`, o pacote acrescenta `auth:{guard}`. Se a lista ficar vazia depois de tirar `web`, o pacote usa `auth:{guard}` e `verified`.

Os assets estáticos ficam fora dessas rotas:

- `/vendor/bruno-docs/swagger-ui.css`
- `/vendor/bruno-docs/swagger-ui-bundle.js`

## O que entra no OpenAPI

O passo a passo da conversão está em [docs/fluxo-bruno-para-swagger.md](docs/fluxo-bruno-para-swagger.md).

A partir dos `.bru` da coleção:

- método, path e `meta.name`; `{{baseUrl}}` sai da URL
- cada `folder.bru` vira tag, ordenada por `seq`; pasta aninhada junta os nomes com ` / `
- `params:query`: linha com `~` é valor desligado; a mesma chave repetida vira `enum`; `ids[]` repetido vira array
- `body:json` vira example e um schema inferido do JSON; JSON inválido omite o `requestBody`
- segmento UUID no path vira parâmetro (`/api/medicaments/{uuid}` vira `/api/medicaments/{medicament}`)
- bloco `docs` vai na description; linhas de status (`401`, `Resposta 200` e um JSON) viram `responses` quando der para extrair; o texto integral permanece na description
- `auth: bearer`, ou herança da collection, aplica `bearerAuth`
- cada `environments/*.bru` vira server só com `vars.baseUrl` em `http://` ou `https://`, nesta ordem: `local`, `development`, `homolog`, `production`, depois o restante em ordem alfabética

`vars:secret` e `api_token` não entram no spec. Scripts Bruno, asserts e multipart ficam de fora.

O cache usa a chave `bruno-docs.` mais a impressão dos `.bru` e do `bruno.json`. Com `BRUNO_DOCS_CACHE=false` o spec é montado em todo pedido.

## Testes

Na pasta do pacote:

```bash
composer install
vendor/bin/phpunit
```

Os testes de parser e builder usam fixtures em `tests/fixtures`. O teste HTTP sobe a página com Orchestra Testbench e a mesma fixture. Um teste do builder lê uma coleção `api-docs` três níveis acima do pacote e é ignorado se essa pasta não existir.
