KunstmaanAnomyBundle
=====================

The Kunstmaan Anomy Bundle provides a symfony command to use a mysql dump and anonymize it wiht Faker.

# Enabling the bundle

Add to Appkernel.php

# Configure the bundle in your config.yml file.

### Configuration reference:

The following parameters need to be provided. The database_user should be a mysql user that can create databases.

```
kunstmaan_anomy:
  config_file: /home/projects/foo//anon.yml
  backup_dir: /home/projects/foo/backup
  database_user: 'root'
  database_password: 'root'

```

### anon.yml file in your project

The entities array contains all tables and the columns which you would like to fake.
Methods can be found at https://github.com/fzaninotto/Faker.

The locale will be used to do some extra stuff with faker locale based like a BTW number.

```
guesser_version: 1.0.0b
locale: nl_BE
entities:
    kuma_users:
        cols:
            username: { method: safeEmail }
            username_canonical: { method: safeEmail }
            email: { method: safeEmail }
            email_canonical: { method: safeEmail } 
```

# Commands

```
php bin/console kuma:anonymize:database
``` 

If you add -v, you can see more information being dumpted to the screen.

### Contributing

We love contributions!
If you're submitting a pull request, please follow the guidelines in the [Submitting pull requests](docs/pull-requests.md)
