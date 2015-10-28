```
# create table
\HKP\ORM::query("DROP TABLE IF EXISTS `t_user`;CREATE TABLE `t_user` (`id` INT,`user` VARCHAR(20),PRIMARY KEY (`id`));");

# user object
$user = new \HKP\ORM('user');
// or run mk()
// $user = new user_orm();

# empty
$ret = $user->find();
if ($ret) die('Error!');

# insert data
$user->add(['id' => 1, 'user' => 'U1']);
$ret = $user->find(1);
if ($ret['user'] != 'U1') die('Error!');

# inset data after set data
$user->id = 2;
$user->add();
$ret = $user->find(2);
if ($ret['user'] != '') die('Error!');

# find by id and update data
$user['user'] = 'U2';
$user->save();
$ret = $user->find(2);
if ($ret['user'] != 'U2') die('Error!');

# find data and update data 2
$user->where('user="U2"');
$user['user'] = 'U3';
$user->save();
$ret = $user->find(2);
if ($ret['user'] != 'U3') die('Error!');

# list data
foreach ($user->clear()->all() as $o) {
    echo $o['id'], ' => ', $o['user'], "\n";
    // or
    // $u = new user_orm($o);
    // echo $o->id, ' => ', $o->getUser(), "\n";
}

# delete by id
$user->find(1);
$user->remove();

# delete from where
//$u3 = new user_orm();
$user->where(['id' => 2]);
$user->remove();

# echo total
$total = $user->clear()->total();
if ($total != 0) die('Error!');

# make orm class
echo \HKP\ORM::mk();

# delete table
\HKP\ORM::query("DROP TABLE IF EXISTS `t_user`;");
```