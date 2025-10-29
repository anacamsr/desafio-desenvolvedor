
# Desafio API de Upload e Busca de Arquivos (OT)

Este projeto implementa um serviço de API para upload robusto de arquivos CSV, rastreamento de hashes para evitar duplicidade e uma API de busca paginada no conteúdo importado.

O ambiente de desenvolvimento é totalmente conteinerizado usando Docker Compose.

## Stack utilizada

**Linguagem:** PHP ^8.2

**Framework:** laravel/framework ^12.0

**Ferramenta de Debug** laravel/tinker ^2.10.1



## Instalação

Siga os passos abaixo para ter o projeto rodando em sua máquina local.

**Pré-requisitos**

- **Docker** (Inclui o Docker Engine e o Docker CLI)

- **Docker Compose** (Geralmente incluído na instalação do Docker Desktop)

- **MySQL**


**Configurar o Ambiente**


Crie o arquivo de variáveis de ambiente (.env) a partir do modelo (.env.example):

```bash
DB_CONNECTION=mysql
DB_HOST=desafio-db-1
DB_PORT=3306
DB_DATABASE=desafio-ot
DB_USERNAME=root
DB_PASSWORD=senhadobancoaqui
```

**Build e Início dos Containers**

Execute o Docker Compose para construir as imagens e iniciar os serviços (PHP, Nginx e MySQL):

```bash
docker-compose up --build -d
```

**Configuração do Laravel e Banco de Dados**

Após os containers estarem rodando, execute os comandos de configuração do Laravel dentro do container da aplicação:

- Instalar Dependências PHP

```bash
docker exec -it desafio-app-1 composer install

```

- Gerar a Chave da Aplicação

```bash
docker exec -it desafio-app-1 php artisan key:generate

```

- Executar Migrações do Banco de Dados

```bash
docker exec -it desafio-app-1 php artisan migrate

```

## Comandos Essenciais para Debug

**Acessar o Log do Laravel**

Use este comando para ver erros de aplicação em tempo real:
```bash
docker exec -it desafio-app-1 tail -f storage/logs/laravel.log

```

**Acessar o Console do Banco de Dados (MySQL)**

Essencial para verificar o status das tabelas ou limpar dados para novos testes:

```bash
docker exec -it desafio-db-1 mysql -u root -p

```

**Comandos de Limpeza (para retestar o upload do mesmo arquivo):**

Após acessar o console MySQL:

```bash
USE `desafio-ot`;
TRUNCATE TABLE uploaded_files;  -- Essencial para remover o hash duplicado
TRUNCATE TABLE file_contents;   -- Para limpar os dados importados

```

**Acessar o Bash do Container da Aplicação**

```bash
docker exec -it desafio-app-1 bash

```


## Documentação Interativa (Swagger UI)

A documentação da API foi gerada usando o padrão OpenAPI (Swagger) e está disponível em uma interface interativa para testes e visualização.

**URL de Acesso:**

```http
  GET http://localhost:8080/api/documentation
```

## Documentação da API

#### Faz upload e importa um arquivo CSV para o banco

- Espera multipart/form-data com o campo file.

```http
  POST http://localhost:8080/api/upload
```

#### Lista o histórico de arquivos enviados

```http
  GET http://localhost:8080/api/history
```

#### Busca e lista o conteúdo importado.

- Retorna dados paginados (20 por página) por padrão.

```http
  GET http://localhost:8080/api/file-content
```

## Desafios

-  O primeiro desafio encontrado foi no Upload: como os arquivos sao grandes, o tamanho do upload nao estava suportando, foram cogitados 2 alternativas, a primeira em diminuir o tamanho do "peso" dos arquivos sem perder os dados, mas essa alternativa não funcionou, então parti para a segunda que era aumentar a mémoria do server com nginx, essa segunda alternativa funcionou.

- Como são arquivos, o histórico não estava retornando no Workbanch, apenas no terminal MySQL.

- O endpoint de busca dentro dos aruivos também foi um desfio, pois como a primeira linha dos arquivos não estavam com o nome das colunas que ele precisava buscar, foi feito um método para que ele buscasse pelas primeiras linhas até achar a o dado correspondente, mas isso causa um leve deley.

- Pontos a se considerar: a pesar dos testes no insomnia funcionarem, retornarem status 200, no upload, depois que inseri a questao de busca dos dados dentro dos arquivos, ele passou a demorar mais a fazer o post e não retorna o status, porém cadastra no banco de dados e retorna todas as requisições testadas.