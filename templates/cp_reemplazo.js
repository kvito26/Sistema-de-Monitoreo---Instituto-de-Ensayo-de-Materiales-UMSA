//=============================================================================================
//PANEL PARA EL AMBIENTE 2
//para el monitor 2
monitor2.classList.remove('bg-success', 'bg-warning');
monitor2.classList.add('bg-danger');

//para el span del ventilador
estadoVent2.innerText = 'SIN DATOS';
estadoVent2.classList.remove('bg-success', 'bg-danger', 'bg-warning');
estadoVent2.classList.add('bg-orange');

//para el span del calentador
estadoCalent2.innerText = 'SIN DATOS';
estadoCalent2.classList.remove('bg-success', 'bg-warning', 'bg-danger');
estadoCalent2.classList.add('bg-orange');

//para el span del humidificador
estadoHumi2.innerText = 'SIN DATOS';
estadoHumi2.classList.remove('bg-success', 'bg-warning', 'bg-danger');
estadoHumi2.classList.add('bg-orange');

//para el span del nivel de agua
estadoNivel2.innerText = 'SIN DATOS';
estadoNivel2.classList.remove('bg-success', 'bg-warning', 'bg-danger');
estadoNivel2.classList.add('bg-orange');
