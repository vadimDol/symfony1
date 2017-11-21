# symfony 1.4

## Установка
------------
1 Способ:




С помощью git
```
    git init # ваш проект
    git submodule add https://github.com/vadimDol/symfony1 vendor/symfony
    git submodule update --init --recursive
```

2 Способ:

С помощью [Composer](https://getcomposer.org/download/) 
```
    composer require vadimdol/symfony1 "1.1.0"
```

------------
## Создание проекта

```
    php vendor\symfony\data\bin\symfony generate:project PROJECT_NAME --orm=Propel 
    или
    php vendor\symfony\data\bin\symfony generate:project PROJECT_NAME  
```

## Создание приложения

```
    php symfony generate:app frontend
```

## Создание модуля


```
    php symfony generate:module frontend hello_world 
```
------------


## Документация
-------------
Официальная документация - [symfony1 documentation](http://symfony.com/legacy)

## Contributing
------------
Вы можете отправить pull requests или создать issue.
