# symfony 1.4

## Установка
------------
1 Способ:
```
    git init # ваш проект
    git submodule add https://github.com/vadimDol/symfony1 vendor/symfony
    git submodule update --init --recursive
```

Скоро будет добавлена возможность установки через composer

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
