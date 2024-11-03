## Introdução

Essa é uma aplicação básica que desenvolvi para integrar com o [Owntracks](https://owntracks.org/) em modo HTTP e exibir todo o histórico de 
localização em um mapa.

## Instalação

A maneira mais fácil de rodar a aplicação é utilizando o Docker. Para isso, basta executar o comando abaixo:

```bash
docker compose up -d
```

Além disso, ao rodar a aplicação pela primeira vez, é necessário criar o banco de dados. Para isso, execute o comando abaixo:

```bash
docker compose run --rm php php scripts/create_db.php
```

A aplicação estará disponível em `http://localhost:9899`

### Configurando o Owntracks para enviar dados de localização

Essa aplicação não coleta os dados de localização via GPS. Ela apenas recebe e armazena os dados enviados pelo Owntracks. 

Para configurar o Owntracks para enviar os dados para a aplicação, siga os passos abaixo:

1. Vá em "Preferências" e depois "Conexão"
2. Em "Conexão", configure a seguir:
   3. Mode: HTTP
   4. URL: http://localhost:9899/record.php (ou o IP/hostname da máquina onde você está rodando a aplicação)

Pronto! A partir desse momento, o Owntracks enviará os dados de localização para a aplicação.

É importante lembrar que o Owntracks possuí diversos modos de rastreamento e isso impacta a quantidade e frequência de dados enviados. Normalmente, sempre que saio de caso, coloco o app no modo "Move" para conseguir um melhor traçado no mapa.
