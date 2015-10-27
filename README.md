```
# insert data
$u1 = \HKP\ORM::init('user');
$u1->add(['id' => 1, 'user' => 'hk001']);

# inset data after set data
$u2 = \HKP\ORM::init('user');
$u2->id = 2;
$u2->add();

# find by id and update data
$u2 = new user();
$u2->find(2);
$u2->setUser('hk002');
$u2->save();

# find data and update data 2
$u2 = new user();
$u2->where(['user' => 'hk002']);
$u2->setUser('hk003');
$u2->save();

# delete by id
$u3 = new user();
$u3->find(3);
$u3->remove();

# delete from where
$u3 = new user();
$u3->where(['id' => 333]);
$u3->remove();

# echo total
echo (new user())->where(['id' => 22])->total();

foreach ((new user())->all() as $o) {
    $u = new user($o);
    echo $u['id'], ' => ', $u->user, "\n";
}

# make orm class
echo \HKP\ORM::mk();
```