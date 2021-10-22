Udelat test:

Pokud Ukazuju s FetchArray na nejakou entitu, a ona ukazuje zpátky s více než jednou 'foreignKey' property, vyhodit
vyjimku!

Otestovat ze pokud ma entita pres FetchArray nejake childy, ale child entita má foreignKey ukazujici na neco jinneho nez
na me/nebo unset tak tu forignKey propertky zmenime na sebe!