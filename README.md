# QUELCODE PHP課題 

## ディレクトリ解説

```
quelcode-php
├── html ....................... ドキュメントルート
├── mysql5.7
│   ├── mysql .................. 起動すると作られる。データ永続化用
│   ├── mysqlvolume ............ mysqlコンテナにマウントされる。ホストとのファイル受け渡し用
│   └── my.cnf ................. mysqlコンテナの設定ファイル
├── php7.2
│   ├── Dockerfile ............. phpコンテナのDockerファイル
│   └── php.ini ................ phpの設定ファイル
├── .gitignore
├── docker-compose.yml
└── README.md
```

## データベース接続情報
MySQL バージョン 5.7.x


### コンテナ内部から接続する場合
```
host:mysql
port:3306
user:test
password:test
dbname:test
```

### Macから接続する場合
```
host:localhost
port:13106
user:test
password:test
dbname:test
```
