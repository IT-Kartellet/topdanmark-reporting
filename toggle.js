function openRow(boxId, toggleId){
    console.log("openRow(" + boxId + ", " + toggleId + ")");

	$(boxId).toggle();
	$("."+toggleId).toggle();
}